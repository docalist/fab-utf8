<?php
/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Ce module permet d'indexer des donn�es SQL pr�sentes dans une base de
 * donn�es relationnelle accessible via PDO (mysql, sqlite, etc.)
 *
 * Les donn�es � indexer sont d�finies via des requ�tes SQL indiqu�es dans la
 * configuration du module.
 *
 * @package     fab
 * @subpackage  modules
 */
class SqlIndex extends Module
{
    /**
     * Cr�e une connexion PDO � la base de donn�es SQL utilis�e pour l'indexation.
     *
     * Par d�faut, la m�thode utilise les informations indiqu�es dans le fichier de
     * configuration du module (dsn, user, password...) pour se connecter � la base.
     *
     * Les modules descendant peuvent surcharger cette m�thode pour mettre en oeuvre d'autres
     * m�thodes de connexion.
     *
     * Par exemple, si les param�tres de connexion sont d�j� disponibles ailleurs (dans le
     * fichier de config de Wordpress, par exemple), il est souhaitable de surcharger cette
     * m�thode pour �viter de dupliquer l'information.
     *
     * @return PDO une connexion ouverte � la base de donn�es.
     * @throws PDOException si la tentative de connexion � la base de donn�es �choue.
     */
    protected function getDatabaseConnection()
    {
        $dsn = Config::get("source.dsn");
        if (empty($dsn))
            throw new Exception("La chaine de connexion � la base de donn�es SQL n'a pas �t� indiqu�e.");

        $username = Config::get("source.username");
        $password = Config::get("source.password");
        $options = (array) Config::get("source.driver_options");

        return new PDO($dsn, $username, $password, $options);
    }


    /**
     * Signale une erreur dans une requ�te SQL.
     *
     * @param PDO|PDOStatement $source la source de l'erreur
     * @param string $sql optionnel, la requ�te sql ex�cut�e.
     */
    protected function reportSqlError($source, $sql=null)
    {
        if (is_null($sql) && $source instanceof PDOStatement) $sql = $source->querystring;

        $error = $source->errorInfo();
        echo "<p>Une erreur est survenue lors de l'ex�cution de la requ�te : <pre>$sql</pre>";
        echo $error[2], "</p>";
    }

    /**
     * Indexe les donn�es
     */
    public function actionIndex($confirm = false)
    {
        // R�cup�re le nom de la base Xapian � cr�er
        $database = Config::get('database');
        if (empty($database))
            throw new Exception("Le nom de la base de donn�es � cr�er n'a pas �t� indiqu�");

        // D�termine son path
        $databasePath = Config::get("db.$database.path", $database);
        if (Utils::isRelativePath($databasePath))
            $databasePath = Utils::makePath(Runtime::$root, 'data/db', $databasePath);

        // R�cup�re le sch�ma � utiliser pour la base xapian
        $schema = Config::get('schema');
        if (empty($schema))
            throw new Exception("Le nom du sch�ma � utiliser n'a pas �t� indiqu�.");

        // D�termine son path
        if (false === $schema=Utils::searchFile($schema, Runtime::$root . 'data/schemas/'))
            throw new Exception('Impossible de trouver le sch�ma indiqu� dans la config.');

        // Cr�e une connexion � la base de donn�es SQL
        $db = $this->getDatabaseConnection();

        echo "<h1>", Config::get('title'), '</h1>';

        // Demande confirmation
        if (!$confirm)
        {
            printf
            (
                "<p>Vous allez lancer l'indexation des donn�es de la base de donn�es <strong>%s</strong> (%s, %s) dans la base Xapian <strong>%s</strong>.</p>",
                Config::get('source.label'),
                $db->getAttribute(PDO::ATTR_DRIVER_NAME),
                $db->getAttribute(PDO::ATTR_CONNECTION_STATUS),
                $database
            );
            echo "<p>La base <strong>$database</strong> va �tre �cras�e puis sera reconstruite.</p>";

            echo '<p><a href="?confirm=true">Cliquez ici pour confirmer que c\'est bien �a que vous voulez faire...</a></p>';
            return;
        }

        // Cr�e une t�che au sein du taskmanager
        if (! User::hasAccess('cli'))
        {
            $id=Task::create()
                ->setRequest($this->request)
                ->setTime(0)
                ->setRepeat(null)
                ->setLabel('Indexation de la base sql ' . Config::get('source.label') . ' dans la base ' . $database)
                ->setStatus(Task::Waiting)
                ->save()
                ->getId();

            Runtime::redirect('/TaskManager/TaskStatus?id='.$id);
        }

        echo "<p>1. Cr�ation de la base xapian $database...</p>";

        // Charge le sch�ma de la base Xapian
        $dbs=new DatabaseSchema(file_get_contents($schema));

        // Cr�e la base Xapian
        echo "<p>2. Connexion � la base de donn�es SQL...</p>";
        $xapianDb=Database::create($database, $dbs, 'xapian');

        // Pre-query
        echo "<p>3. Ex�cution des requ�tes de pr�-traitement...</p>";
        foreach ((array) Config::get("data.before") as $sql)
            if (false === $result = $db->exec($sql))
                return $this->reportSqlError($db, $sql);


        echo "<p>4. Indexation des donn�es...</p>";
        $total = 0;
        echo '<ul>';
        foreach((array)Config::get("data.datasets") as $name=>$dataset)
        {
            echo "<li>";
            echo "<p>Jeu de donn�es ", is_int($name) ? ("num�ro ".($name+1)) : "\"$name\"", "...</p>";
            $nb = 0;

            // Prepare les statements pour les "other-fields"
            echo "<p>Pr�paration des requ�tes 'other-fields'...</p>";
            $other = array(); // chaque item contient : le statement et un tableau contenant les noms des param�tres
            foreach((array)Config::get("data.datasets.$name.other-fields") as $sql)
            {
                $statement = $db->prepare($sql);
                $matches = array();
                preg_match_all('~:([a-z0-9_]+)~i', $sql, $matches);
                $other[] = array($statement, $matches[1]);
            }

            // Ex�cute la requ�te principale
            echo "<p>Ex�cution de la requ�te principale...</p>";
            $sql = Config::get("data.datasets.$name.query");
            if (false === $records = $db->query($sql, PDO::FETCH_ASSOC))
            {
                return $this->reportSqlError($db, $sql);
            }

            // Indexe toutes les r�ponses
            echo "<p>Indexation...</p>";
            foreach($records as $record)
            {
                // Ex�cute chacune des requ�tes "other-field" pour le record obtenu.
                foreach($other as $item)
                {
                    $statement = $item[0];
                    foreach($item[1] as $field)
                        $statement->bindValue($field, $record[$field]);

                    if (! $statement->execute())
                        return $this->reportSqlError($statement);

                    foreach($statement->fetchAll(PDO::FETCH_ASSOC) as $row)
                    {
                        foreach($row as $field=>$value)
                        {
                            if (isset($record[$field]))
                            {
                                if (is_array($record[$field]))
                                    $record[$field][] = $value;
                                else
                                    $record[$field] = array($record[$field], $value);
                            }
                            else
                            {
                                $record[$field] = $value;
                            }
                        }
                    }
                }

                // Eclate les champs en champs articles
                foreach((array)Config::get("data.datasets.$name.split") as $field => $sep)
                    if (isset($record[$field]))
                        $record[$field] = explode(trim($sep), $record[$field]);

                // Cr�e l'enregistrement xapian
                $xapianDb->addRecord();
                foreach($record as $field=>$value)
                {
                    if (is_array($value))
                        foreach ($value as $item)
                            $item = strip_tags($item);
                    else
                        $value = strip_tags($value);

                    $xapianDb[$field] = $value;
                }
                $xapianDb->saveRecord();
                ++$nb;

                // debug
//                $record['Content'] = 'supprim�';
//                echo "<pre>", var_export($record, true), "</pre>";
            }
            echo Utils::pluralize('<p>%d enregistrement{s} ajout�{s} dans la base Xapian.</p>', $nb);
            echo "</li>";
            $total += $nb;
        }
        echo '</ul>';

        // Post-query
        echo "<p>Ex�cution des requ�tes de post-traitement...</p>";
        foreach ((array) Config::get("data.after") as $sql)
            if (false === $result = $db->exec($sql))
                return $this->reportSqlError($db, $sql);

        echo Utils::pluralize('<p>Termin�. La base Xapian contient %d enregistrement{s} au total.</p>', $total);
    }
}