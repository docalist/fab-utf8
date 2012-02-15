<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: AdminSchemas.php 1196 2010-07-16 16:21:13Z daniel.menard.bdsp $
 */

use Fooltext\Schema\NodeNames;

use Fooltext\Schema\Schema;
use Fooltext\Schema\Collection;
use Fooltext\Schema\Field;
use Fooltext\Schema\Alias;

/**
 * Module d'administration des schémas.
 *
 * @package     fab
 * @subpackage  Admin
 */
/**
 * Module d'administration permettant de gérer les {@link DatabaseSchema schémas
 * de bases de données de l'application}.
 *
 * Les schémas de bases de données sont des fichiers
 * {@link http://fr.wikipedia.org/wiki/Xml xml} stockés dans le répertoire
 * <code>/data/schemas</code> de l'application et qui définissent la structure
 * d'une base de données (liste des champs, des index, paramètres, etc.)
 *
 * Ce module permet de lister tous les schémas de l'application ainsi que les
 * {@link /AdminSchemas modèles de schémas proposés par fab}.
 *
 * Il hérite du module {@link AdminFiles} et dispose donc des actions
 * de base permettant de {@link AdminFiles::actionCopy() copier},
 * {@link AdminFiles::actionRename() renommer},
 * {@link AdminFiles::actionDelete() supprimer} et
 * {@link AdminFiles::actionDownload() télécharger} un fichier de schéma ainsi
 * que de la possibilité {@link actionEdit() d'éditer le code xml} d'un schéma
 * ou de {@link AdminFiles::copyFrom() créer un nouveau schéma à partir d'un
 * modèle proposé par fab}.
 *
 * <code>AdminSchemas</code> introduit un éditeur spécifique qui permet de
 * {@link actionNew() créer}, {@link actionEditSchema() modifier} et
 * {@link actionSaveSchema() sauvegarder} un schéma de façon plus intuitive
 * qu'en intervenant sur le code xml sous-jacent et dispose d'une fonction de
 * validation intégrée qui empêche la création d'un
 * {@link DatabaseSchema::validate() schéma invalide}.
 *
 * Pour plus d'informations, vous pouvez consulter :
 * - La documentation de l'action {@link AdminDatabases::actionSetSchema SetSchema}
 *   du module d'administration {@link /AdminDatabases AdminDatabases} qui permet
 *   d'appliquer un schéma à une base de données.
 * - {@link DatabaseSchema l'API de la classe DatabaseSchema}.
 *
 * @package     fab
 * @subpackage  Admin
 */
class AdminSchemas extends AdminFiles
{
    /**
     * Page d'accueil du module d'administration des schémas de bases de données.
     *
     * Affiche la liste des schémas disponibles.
     */
    public function actionIndex()
    {
        Template::run
        (
            Config::get('template')
        );
    }

    /**
     * Retourne la liste des schémas connus du système.
     *
     * Par défaut, la fonction retourne la liste des schémas de l'application.
     * Si $fab est à true, c'est la liste des schémas de fab qui sera retournée.
     *
     * @param bool $fab true pour récupérer les schémas définis dans fab,
     * false (valeur par défaut) pour retourner les schémas de l'application.
     *
     * @return array un tableau contenant le path de tous les schémas
     * disponibles (la clé associé contient le nom du schéma, c'est-à-dire
     * <code>basename($path)</code>).
     */
    public static function getSchemas($fab=false)
    {
        $path=($fab ? Runtime::$fabRoot : Runtime::$root) . 'data/schemas/';

        // Construit la liste
        $files=glob($path.'*.xml');
        if ($files===false) return array();

        $schemas=array();
        foreach($files as $file)
        {
            $schemas[$file]=basename($file);
        }

        // Trie par ordre alphabétique du nom
        uksort($schemas, 'strcoll');

        return $schemas;
    }


    /**
     * Retourne le schéma dont le nom est passé en paramètre.
     *
     * @return DatabaseSchema|false
     */
    public static function getSchema($schema)
    {
        $path='data/schemas/';
        $path=Utils::searchFile($schema, Runtime::$root . $path, Runtime::$fabRoot . $path);
        if ($path === false) return false;
        return Schema::fromXml(file_get_contents($path));
    }

    /**
     * Retourne les propriétés du noeud passé en paramètre sous la forme d'une
     * chaine encodée en JSON.
     *
     * Le code JSON retourné ne contient que les propriétés de base, pas les collections
     * (i.e. les propriétés de type "Nodes" sont supprimées).
     *
     * Cette méthode est utilisée dans le template de EditSchema pour stocker les
     * propriétés du noeud dans l'attribut data du tag.
     *
     * @param Fooltext\Schema\Node $node
     * @return string
     */
    public function nodeProperties(Fooltext\Schema\BaseNode $node)
    {
        $data = $node->data;
        foreach($data as $name => $value)
        {
            if ($node->propertyIsIgnored($name)) unset($data[$name]);
            if ($value instanceof Fooltext\Schema\Nodes) unset($data[$name]);
            if ($value instanceof Fooltext\Schema\NodeNames)
            {
                $data[$name] = array_values($value->getData());
            }
        }
//         echo "<pre>"; print_r($data); echo "</pre>";
        return htmlspecialchars(json_encode($data));
    }

public function getTestSchema()
{
    return new Fooltext\Schema\Schema
    (
            array
            (
                    'stopwords' => 'le la les de du des a c en',
                    'document' => 'Notice',
                    'label' => 'test',
                    'fields' => array
                    (
                            array('name'=>'REF'),
                            array('name'=>'Type'),
                            array('name'=>'Titre'),
                            array('name'=>'ISBN'),
                            array('name'=>'Visible'),
                            array
                            (
                                    'name'=>'AutPhys',
                                    'fields' => array
                                    (
                                            array('name'=>'firstname'),
                                            array('name'=>'surname'),
                                            array('name'=>'role')
                                    )
                            )
                    ),
                    'indices' => array
                    (
                            array('name'=>'REF', 'fields'=>array('REF', 'ISBN')),
                            array('name'=>'TI', 'fields'=>array('Titre')),
                            array('name'=>'AU', 'fields'=>array('AutPhys.firstname', 'AutPhys.surname'))
                    ),
                    'aliases' => array
                    (
                            array('name'=>'id', 'indices' => array('ref')),
                            array('name'=>'default', 'indices' => array('ref', 'ti','au'))
                    ),
            )
    );
}
    /**
     * Edite un schéma de l'application.
     *
     * @param string $file le nom du fichier xml à éditer
     */
    public function actionEditSchema($file)
    {
        $dir='data/schemas/';

        // Vérifie que le fichier indiqué existe
        $path=Runtime::$root.$dir.$file;
        if (! file_exists($path))
            throw new Exception("Le schéma $file n'existe pas.");

        // Charge le schéma
         $schema = Fooltext\Schema\Schema::fromXml(file_get_contents($path));
//$schema = $this->getTestSchema();
        // Valide et redresse le schéma, ignore les éventuelles erreurs
        $errors = array();
        if (! $schema->validate($errors))
            throw new Exception("Impossible d'éditer ce schéma, il contient des erreurs : " . implode("\n", $errors));

        // Crée la config (liste des tables, liste des analyseurs, etc.
        $config = new stdClass();
        $config->analyzer = $this->getAnalyzers();
        $config->datasource = array('Codes pays', 'Codes langues');

        // Charge le schéma dans l'éditeur
        return Response::create('html')->setTemplate($this, Config::get('template'), array(
            'schema' => $schema,
            'saveUrl' => 'SaveSchema',
            'file' => $file,
            'config' => $config,
        ));
    }

    protected function getClassDoc($class)
    {
        require_once Runtime::$fabRoot . 'modules/AutoDoc/AutoDoc.php';

        $r = new ReflectionClass($class);
        $doc = $r->getDocComment();

        Config::set('admonitions', array()); // bidouille
        $doc = new DocBlock($doc);

        $doc = $doc->shortDescription . "\n" . $doc->longDescription;
        $doc = strtr($doc, array("<li>" =>"- "));
        $doc = strip_tags($doc);
        $doc = strtr($doc, array("\n " =>" \n"));
        //$doc = html_entity_decode($doc);
        $doc = html_entity_decode($doc, ENT_QUOTES, 'UTF-8');

        $doc = trim($doc);
        return $doc;
    }

    /**
     * Retourne la liste des analyseurs qui sont définis dans la config.
     *
     * Utilisé par l'éditeur de schémas (cf actionEditSchema).
     */
    protected function getAnalyzers()
    {
        $result = array();
        $t = Config::get('analyzer');
        foreach($t as & $group)
        {
            foreach($group['items'] as & $class)
            {
                $class = array
                (
                    'name' => substr($class, strrpos($class, '\\') + 1),
                    'class' => $class,
                    'doc' => $this->getClassDoc($class),
                );
            }
        }
        return $t;
    }

    /**
     * Vérifie et sauvegarde un schéma.
     *
     * Cette action permet d'enregistrer un schéma modifié avec l'éditeur de
     * structure.
     *
     * Elle commence par valider le schéma passé en paramètre. Si des
     * erreurs sont détectées, une réponse au format JSON est générée. Cette
     * réponse contient un tableau contenant la liste des erreurs rencontrées.
     * La réponse sera interprétée par l'éditeur de schéma qui affiche la
     * liste des erreurs à l'utilisateur.
     *
     * Si aucune erreur n'a été détectée, le schéma est enregistré.
     * Dans ce cas, une chaine de caractères au format JSON est retournée
     * à l'éditeur. Elle indique l'url vers laquelle l'utilisateur va être
     * redirigé.
     *
     * @param string $file le nom du fichier xml dans lequel enregistrer le
     * schéma.
     *
     * @param string $schema une chaine de caractères au format JSON contenant le
     * schéma à valider et à enregistrer.
     *
     * @throws Exception si le fichier indiqué n'existe pas.
     */
    public function actionSaveSchema($file, $schema)
    {
//        $file = 'test.xml';

//        require(Runtime::$fabRoot.('/core/database/Schema.php'));

        // Vérifie que le fichier indiqué existe
        $path=Runtime::$root . 'data/schemas/' . $file;
        if (! file_exists($path))
        {
            throw new Exception("Le schéma $file n'existe pas.");
        }

        // Essaie de charger le schéma
        try
        {
            $schema = Schema::fromJson($schema);
        }
        catch (Exception $e)
        {
            return Response::create('JSON')->setContent(array($e->getMessage()));
        }


        // Valide le schéma, détecte les erreurs éventuelles, attribue des ID, etc.
        // S'il y a des erreurs, retourne un tableau JSON contenant la liste des erreurs
        $errors = array();
        if (! $schema->validate($errors))
        {
            return Response::create('JSON')->setContent($errors);
        }

        // Met à jour la date de dernière modification (et de création éventuellement)
        //$schema->setLastUpdate();

        // Sauvegarde le schéma
        file_put_contents($path, $schema->toXml(true));

        // Retourne l'url vers laquelle on redirige l'utilisateur
        return Response::create('JSON')->setContent
        (
            Routing::linkFor($this->request->clear()->setAction('index'), true)
        );
    }


    /**
     * Crée un nouveau schéma (vide).
     *
     * Vérifie que le fichier indiqué existe, demande le nouveau nom
     * du fichier, vérifie que ce nom n'est pas déjà pris, renomme le
     * fichier, redirige vers la page d'accueil.
     */
    public function actionNew($file='')
    {
        $dir='data/schemas/';

        $error='';
        if ($file !== '')
        {
            // Ajoute l'extension '.xml' si nécessaire
            $file=Utils::defaultExtension($file, '.xml');
            if (Utils::getExtension($file) !== '.xml')
                $file.='.xml';

            // Vérifie que le fichier indiqué n'existe pas déjà
            $path=Runtime::$root.$dir.$file;
            if ($file !== '' && file_exists($path))
                $error='Il existe déjà un fichier portant ce nom.';
        }

        if ($file==='' || $error !='')
        {
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'error'=>$error)
            );
            return;
        }

        // Crée un nouveau schéma
        $schema=new DatabaseSchema();

        // Enregistre le schéma dans le fichier indiqué
        file_put_contents($path, $schema->toXml());

        Runtime::redirect('/'.$this->module);
    }

    /**
     * Slot permettant à un autre module de choisir un schéma.
     *
     * @param string $link le lien à appliquer à chaque schéma.
     * @param bool $fab true pou afficher les schémas de l'application,
     * false pour afficher ceux de fab.
     */
    public function actionChoose($link='Edit?file=%s', $fab=false)
    {
        Template::run
        (
            Config::get('template'),
            array('link'=>$link, 'fab'=>$fab)
        );
    }
}
?>