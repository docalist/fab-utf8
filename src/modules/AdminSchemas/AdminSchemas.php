<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel Ménard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: AdminSchemas.php 1196 2010-07-16 16:21:13Z daniel.menard.bdsp $
 */

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
        return new DatabaseSchema(file_get_contents($path));
    }

    public function getPropertiesAsAttributes(fab\Schema\Node $node)
    {
        $h='"'; // ferme 'l'attribut <hack="> ouvert dans le template
        foreach($node->getProperties() as $key=>$value)
        {
//            if ($key === 'name') continue;
            if (is_array($value)) continue;
            if (is_object($value)) continue;
            if (is_bool($value)) $value = $value ? 'true' : 'false';

            $h.= ' data-' . $key . '="' . htmlspecialchars($value) . '"';
        }
        $h .= ' hack2="'; // contrecarre le guillemet fermant de <hack=">
        return $h;
    }

    /**
     * Edite un schéma de l'application.
     *
     * @param string $file le nom du fichier xml à éditer
     */
    public function actionEditSchema($file)
    {
        require(Runtime::$fabRoot.('/core/database/Schema.php'));

//         echo "<pre>", var_dump(new fab\Schema\SortKey,true), "</pre>";
//         return;

        $dir='data/schemas/';

        // Vérifie que le fichier indiqué existe
        $path=Runtime::$root.$dir.$file;
        if (! file_exists($path))
            throw new Exception("Le schéma $file n'existe pas.");

//         $schema = fab\Schema::fromXml(file_get_contents($path));
//         echo '<pre style="overflow: hidden;width: 49%; float: left; border : 1px solid red">';
//         var_dump($schema);
//         echo "</pre>";
//         return;

        // Charge le schéma
//         $schema=new DatabaseSchema(file_get_contents($path));
        $schema = fab\Schema::fromXml(file_get_contents($path));

        // Valide et redresse le schéma, ignore les éventuelles erreurs
//        $schema->validate();
//        $schema=Utils::utf8Encode($schema);
/*
        $props = array('version', 'creation', 'lastupdate', 'label','description', 'stopwords', 'indexstopwords');
        $schema->properties = array();
        foreach($props as $prop)
            $schema->properties[$prop] = $schema->$prop;
*/

        $types = array();
        foreach (fab\Schema\NodesTypes::all() as $type=>$class)
        {
            $node = array();

            // Fils autorisés
            if (is_subclass_of($class, 'fab\Schema\NodesCollection'))
                $node['valid_children'] = $class::getValidChildren();
            else
                $node['valid_children'] = array();

            // Propriétés par défaut du noeud
            $node['defaults'] = $class::getDefaultProperties();

            // Icones
            $icons = $class::getIcons();
            $path = Routing::linkFor("/FabWeb/modules/AdminSchemas/images");
            foreach($icons as $key => & $icon)
                $icon = "$path/$icon";
            $node['icon'] = $icons;

            // Libellés à utiliser
            $node['label'] = $class::getLabels();

            $types[$type] = $node;
        }
        $treeConfig = array
        (
        	'types' => array
        	(
        	    'valid_children' => 'schema',
        	    'types' => $types
        	)
    	);
//         echo '<pre>', var_export($treeConfig,true), '</pre>';
// return;
        // Charge le schéma dans l'éditeur
        Template::run
        (
            Config::get('template'),
            array
            (
                'schema'=>$schema, // hum.... envoie de l'utf-8 dans une page html déclarée en iso-8859-1...
        		//'schema'=>$schema->toJson(), // hum.... envoie de l'utf-8 dans une page html déclarée en iso-8859-1...
                'saveUrl'=>'SaveSchema',
                'saveParams'=>"{file:'$file'}",
                'title'=>'Modification de '.$file,
                'file'=>$file,
                'treeConfig'=>$treeConfig,
            )
        );
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
        require(Runtime::$fabRoot.('/core/database/Schema.php'));
/*
        echo "<pre>";
        var_export($schema);
        echo "</pre>";

        $o=Utils::utf8Decode(json_decode(Utils::utf8Encode($schema), true));
        echo "<pre>";
        var_export($o);
        echo "</pre>";
        return;
        $dir='data/schemas/';

        // Vérifie que le fichier indiqué existe
        $path=Runtime::$root.$dir.$file;
        if (! file_exists($path))
            throw new Exception("Le schéma $file n'existe pas.");
*/
        // Charge le schéma
        //$schema=new DatabaseSchema($schema);
//         echo "<pre>$schema</pre>";
//         return;

$schema = fab\Schema::fromJson($schema);

// echo '<pre style="overflow: hidden;width: 49%; float: left; border : 1px solid red">';
// print_r($schema);
// echo "</pre>";
// return;
//$xml = $schema->toXml(true);
// $xml = $schema->toJson(4);
// echo '<pre>', htmlentities($xml, ENT_NOQUOTES, 'utf-8'), '</pre>';
// return;

// echo '<pre style="overflow: hidden;width: 49%; float: left; border : 1px solid red">';
// print_r($schema);
// echo "</pre>";

$xml = $schema->toXml(true);
echo '<pre>', htmlentities($xml, ENT_NOQUOTES, 'utf-8'), '</pre>';
return

$test = fab\Schema::fromXml($xml);
echo '<pre style="overflow: hidden;width: 49%; float: left; border : 1px solid red">';
print_r($test);
echo "</pre>";
//         echo json_encode($schema);
//         echo json_last_error();
        //echo $schema->toXml();
return;
        // Valide le schéma et détecte les erreurs éventuelles
        $result=$schema->validate();

        // S'il y a des erreurs, retourne un tableau JSON contenant la liste
        if ($result !== true)
            return Response::create('JSON')->setContent($result);

        // Compile le schéma (attribution des ID, etc.)
        $schema->compile();

        // Met à jour la date de dernière modification (et de création éventuellement)
        $schema->setLastUpdate();

        // Aucune erreur : sauvegarde le schéma
        file_put_contents($path, $schema->toXml());

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