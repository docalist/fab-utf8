<?php

//$t=array('a'=>'A', 'b'=>'B', 'c'=>'C');
////$t2=array_map(null, $t, array());
//$t2=array_fill_keys(array_keys($t), null);
//
//var_export($t2);
//die();
define('DB_PATH', Runtime::$root.'data/db/ascodocpsy');
define('BIS_PATH', Runtime::$root.'data/db/ascodocpsy.bed');

/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id
 */

/**
 * Module d'administration des bases de donn�es
 * 
 * @package     fab
 * @subpackage  modules
 */

class DatabaseAdmin extends Module
{
    /**
     * @var XapianDatabaseDriver
     */
    public $selection;
    
    /*
     * affiche la liste des bases de donn�es r�f�renc�es dans db.config
     * et un bouton permettant de cr�er une nouvelle base
     */
    public function NUactionIndex()
    {
        
        Template::run
        (
            'dblist.htm',
            array
            (
                'databases'=>$this->getDatabases(),
            )
        );
    }

    
    /**
     * Construit la liste des bases de donn�es connues du syst�me (i.e. 
     * r�f�renc�es dans le fichier de configuration db.config)
     * 
     * Pour chaque base de donn�es, d�termine quelques informations telles
     * que son type, son path, la taille, le nombre d'enregistrements, etc.
     * 
     * @return array
     */
    private function NUgetDatabases()
    {
        $databases=array();
        
        foreach(Config::get('db') as $name=>$info)
        {
            $db=new StdClass();
            $db->error=null;
            $db->name=$name;
            $db->type=$info['type'];

            if (Utils::isRelativePath($info['path']))
                $db->path=Utils::searchFile($info['path'], Runtime::$root . 'data/db');
            else
                $db->path=$info['path'];
                
            $db->size=Utils::dirSize($db->path);
            
            try
            {
                $base=Database::open($name);
            }
            catch (Exception $e)
            {
                $db->error=$e->getMessage();
            }
            $db->count=$base->totalCount();
            $db->lastDocId=$base->lastDocId();
            $db->averageLength=$base->averageLength();
            
            $databases[]=$db;
        }
        return $databases;
    }
    
    /**
     * Construit la liste des sch�mas disponibles
     *
     * @return array(DatabaseSchema) un tableau contenant tous les sch�mas 
     * disponibles.
     */
    private static function NUgetTemplates()
    {
        $templates=array();
        
        // Construit la liste
        foreach(array(Runtime::$fabRoot, Runtime::$root) as $path)
        {
            $path.='data/schemas/';
            if (false === $files=glob($path.'*.xml')) continue;

            foreach($files as $file)
            {
                try
                {
                    $name=basename($file);
                    $templates[basename($file)]=new DatabaseSchema(file_get_contents($file));
                }
                catch(Exception $e)
                {
                    echo 'Impossible de charger le mod�le ', $file, ' : ', $e->getMessage(), '<br />';
                }
            }
        }
        
        // Trie par ordre alphab�tique du nom
        uksort($templates, 'strcoll');

        return $templates;
    }

    
    /**
     * Cr�e une nouvelle base de donn�es
     * 
     * - aucun param�tre : affiche un formulaire "saisie du nom, choix du template"
     * - les deux : cr�e la base, redirige vers EditSchema?type=db&name=xxx
     * 
     * @param string name le nom de la base de donn�es � cr�er
     * @param string def le nom du sch�ma contenant la structure de la base de 
     * donn�es � cr�er
     * 
     * @throws Exception s'il existe d�j� une base de donn�es ayant le nom
     * indiqu� ou si le template sp�cifi� n'existe pas
     */
    public function NUactionNewDatabase()
    {
        $dir='data/schemas/';
        $fabDir=Runtime::$fabRoot.$dir;
        $appDir=Runtime::$root.$dir;
        
        // R�cup�re les param�tres
        $name=Utils::get($_GET['name']);
        $template=Utils::get($_GET['template']);
        $errors=array();

        // On nous a pass� des param�tres : on les v�rifie et on cr�e la base
        if (isset($_GET['name']) || isset($_GET['template']))
        {
            // V�rifie le nom de la base
            if (!$name)
                $errors[]='Veuillez indiquer le nom de la base � cr�er';
            elseif (Config::get('db.'.$name))
                $errors[]='Il existe d�j� un alias de base de donn�es qui porte ce nom';
            elseif(is_dir($path=Runtime::$root.'data/db/'.$name) || is_file($path))
                $errors[]='Il existe d�j� un fichier ou un r�pertoire avec ce nom dans le r�pertoire data/db';
            
            // V�rifie le template indiqu�
            if (! $template)
                $errors[]="Veuillez choisir l'un des mod�les propos�s";
            elseif (!$template=Utils::searchFile($template, $appDir, $fabDir))
                $errors[]='Impossible de trouver le mod�le indiqu�';
            else
            {
                $dbs=new DatabaseSchema(file_get_contents($template));
            }
            
            // Aucune erreur : cr�e la base
            if (!$errors)
            {
                // Cr�e la base
                $db=Database::create($path, $dbs, 'xapian');

                // Ajoute un alias dans db.yaml
                throw new Exception('Non implement� : ajouter un alias dans le fichier xml db.config');
                $configPath=Runtime::$root.'config' . DIRECTORY_SEPARATOR . 'db.yaml';
                $t=Config::loadFile($configPath);
                $t[$name]=array('type'=>'xapian', 'path'=>$path);
                Utils::saveYaml($t, $configPath);
                
                // Ok
                Template::run
                (
                    "databaseCreated.html",
                    array
                    (
                        'name'=>$name,
                        'path'=>$path
                    )
                );
                return;
            }
        }
        
        // Aucun param�tre ou erreur : affiche le formulaire 
        Template::run
        (
            'newDatabase.html',
            array
            (
                'name'=>$name,
                'template'=>$template,
                'templates'=>self::getTemplates(),
                'error'=>implode('<br />', $errors)
            )
        );
    }
    
    public function NUactionSetSchema($database, $schema, $confirm=false)
    {
        // D�termine le path exact du sch�ma indiqu�
        $schema=Utils::defaultExtension($schema, '.xml');
        $path=Utils::searchFile
        (
            'data/schemas/' . $schema,
            Runtime::$root, 
            Runtime::$fabRoot
        );
        
        if ($path === false)
            throw new Exception("Impossible de trouver le sch�ma indiqu� : $schema");
            
        // Charge le sch�ma
        $newDbs=new DatabaseSchema(file_get_contents($path));
        
        // Ouvre la base de donn�es et r�cup�re le sch�ma actuel de la base
        $this->selection=Database::open($database, !$confirm); // confirm=false -> readonly=true, confirm=true->readonly=false
        $oldDbs=$this->selection->getSchema();
        
        // Compare l'ancien et la nouveau sch�mas
        $changes=$newDbs->compare($oldDbs);

        // variables utilitaires pour avoir des liens sur le nom de la base et du sch�ma
        $linkDatabase=sprintf
        (
            '<a href="%s" title="%s"><strong>%s</strong></a>',
            Routing::linkFor('/DatabaseInspector/SearchForm?database=' . $database),
            'Inspecter...', 
            $database
        );
        $linkSchema=sprintf
        (
            '<a href="%s" title="%s"><strong>%s</strong></a>',
            Routing::linkFor('/DatabaseAdmin/EditSchema?template=' . $schema), 
            'Editer...', 
            $schema
        );
        
        // Affiche une erreur si aucune modification n'a �t� apport�e
        if (count($changes)===0)
        {
            echo "<p>La base de donn�es $linkDatabase et le mod�le $linkSchema",
                 ' ont la m�me structure, aucune modification n\'est n�cessaire.</p>';
            return;
        }

        // Affiche la liste des modifications apport�es et demande confirmation � l'utilisateur
        if (! $confirm)
        {
            // Affiche le template de confirmation
            Template::run
            (
                config::get('template'),
                array
                (
                    'confirm'=>$confirm, 
                    'database'=>$database, 
                    'schema'=>$schema, 
                    'changes'=>$changes,
                    'linkDatabase'=>$linkDatabase,
                    'linkSchema'=>$linkSchema
                )
            );
            
            return;
        }
        
        // Applique la nouvelle structure � la base
        $this->selection->setSchema($newDbs);
        
        // Affiche le r�sultat et propose (�ventuellement) de r�indexer
        Template::run
        (
            config::get('template'),
            array
            (
                'confirm'=>$confirm, 
                'database'=>$database, 
                'schema'=>$schema, 
                'changes'=>$changes,
                'linkDatabase'=>$linkDatabase,
                'linkSchema'=>$linkSchema
            )
        );
    }
    
    public function actionCompare($old,$new)
    {
        //header('content-type: text/plain;charset=ISO-8859-1;');
        
        $dir=Runtime::$root . 'data/schemas/';
        
        $old=Utils::defaultExtension($old, '.xml');
        $new=Utils::defaultExtension($new, '.xml');
        
        echo '<h1>Comparaison des sch�mas ', $old, ' et ', $new, '</h1>';
        
        $old=new DatabaseSchema(file_get_contents($dir.$old));
        $new=new DatabaseSchema(file_get_contents($dir.$new));
        
        $changes=$new->compare($old);
        
        
        if (count($changes)===0)
            echo '<p>Aucune modification n\'a �t� d�tect�e.</p>';
        else
        {
            echo '<ul>', "\n";
            $color=array(0=>'green', 1=>'orange', '2'=>'red');
            foreach($changes as $change=>$level)
            {
                echo '    <li style="color:',$color[$level], '">', $change, ' (', $level, ')', '</li>', "\n";
            }
            echo '</ul>', "\n";
            
            echo '<div style="border: 1px solid #000; background-color: #fff; font-size: 1.5em; margin: 1em; padding: 0 1em">';
            switch(max($changes))
            {
                case 0:
                    echo '<p style="color:green">Toutes les modifications peuvent �tre prises en compte imm�diatement, il est inutile de r�indexer la base (0).</p>';
                    break;
                case 1:
                    echo '<p style="color:orange">Les modifications apport�es peuvent �tre prises en compte imm�diatement, mais le fait de r�indexer la base permettrait de purger les donn�es qui ne sont plus n�cessaires (1).</p>';
                    break;
                case 2:
                    echo '<p style="color:red">Une ou plusieurs des modifications apport�es n�cessitent une r�indexation de la base (2).</p>';
                    break;
                default: 
                    echo '<p>gloups! max erron�</p>';
            }
            echo '</div>';
        }
    }
    
    /**
     * Cr�e ou �dite un sch�ma
     *
     * @return unknown
     */
    public function NUactionEditSchema($template='', $new=false)
    {
        $dir='data/schemas/';
        $fabDir=Runtime::$fabRoot.$dir;
        $appDir=Runtime::$root.$dir;

        $dbs=null;
        $errors=array();
        
        if ($template)
        {
            // Cas 1 : Cr�ation d'un nouveau mod�le
            if ($new)
            { 
                // V�rifie que le mod�le � cr�er n'existe pas d�j�
                $template=Utils::defaultExtension($template, '.xml');
                if (file_exists($appDir.$template))
                {
                    $errors[]="Il existe d�j� un sch�ma portant ce nom : '" . $template . '"';
                }
                else
                {
                    $dbs=new DatabaseSchema();
                    file_put_contents($appDir.$template,$dbs->toXml());
                }
                $title='Cr�ation du sch�ma '.$template;
            }
            
            // Cas 2 : �dition d'un mod�le existant
            else
            {
                // Cas 2.1 : on �dite un mod�le de l'application
                if (file_exists($appDir.$template))
                {
                    $dbs=new DatabaseSchema(file_get_contents($appDir.$template));
                }
    
                else
                {            
                    // Cas 2.2 : c'est un template de fab, on le recopie dans app
                    if (file_exists($fabDir.$template))
                    {
                        $dbs=new DatabaseSchema(file_get_contents($fabDir.$template));
                        file_put_contents($appDir.$template,$dbs->toXml());
                    }
                    else
                    {
                        $errors[]='Le fichier indiqu� n\'existe pas : "' . $template . '"';
                    }
                }
                $title='Modification du sch�ma '.$template;
            }
        }
                
        // Aucun param�tre ou erreur : affiche la liste des templates disponibles
        if (! $dbs)
        {
            return Template::run
            (
                'schemas.html',
                array
                (
                    'templates'=>self::getTemplates(),
                    'error'=>implode('<br />', $errors),
                    'template'=>$template
                )
            );
        }
        
        // Valide et redresse le sch�ma, ignore les �ventuelles erreurs
        $dbs->validate();

        // Charge le sch�ma dans l'�diteur
        Template::run
        (
            'dbedit.html',
            array
            (
                'schema'=>$dbs->toJson(), // hum.... envoie de l'utf-8 dans une page html d�clar�e en iso-8859-1...
                'saveUrl'=>'SaveSchema',
                'saveParams'=>"{template:'$template'}",
                'title'=>$title
            )
        );
    }
    
    /**
     * V�rifie et sauvegarde le sch�ma.
     * 
     * Cette action permet d'enregistrer un sch�ma modifi� avec l'�diteur de 
     * structure.
     * 
     * Elle commence par valider le sch�ma pass� en param�tre. Si des 
     * erreurs sont d�tect�es, une r�ponse au format JSON est g�n�r�e. Cette
     * r�ponse contient un tableau contenant la liste des erreurs rencontr�es.
     * La r�ponse sera interpr�t�e par l'�diteur de sch�ma qui affiche la
     * liste des erreurs � l'utilisateur.
     * 
     * Si aucune erreur n'a �t� d�tect�e, le sch�ma va �tre enregistr�.
     * L'endroit o� le sch�ma va �tre enregistr�e va �tre d�termin� par les
     * variables pass�es en param�tre. Pour �viter de faire appara�tre des 
     * path complets dans les url (ce qui pr�senterait un risque de s�curit�),
     * la destination est d�termin�e par deux variables (type et name) qui sont 
     * d�taill�es ci-dessous. Une fois le nouveau sch�ma enregistr�, une
     * chaine de caract�res au format JSON est retourn�e � l'�diteur. Elle 
     * indique l'url vers laquelle l'utilisateur va �tre redirig�. 
     * 
     * @param string json une chaine de caract�res au format JSON contenant le
     * sch�ma � valider et � enregistrer.
     * 
     * @param string type le type du fichier dans lequel le sch�ma sera 
     * enregistr� si il est correct. 
     * 
     * Type peut prendre les valeurs suivantes :
     * <li>'fab' : un mod�le de fab</li>
     * <li>'app' : un mod�le de l'application</li>
     * 
     * @param string name le nom du fichier dans lequel le mod�le sera enregistr�. 
     *
     */
    public function NUactionSaveSchema()
    {
        $json=Utils::get($_POST['schema']);
        
        $dbs=new DatabaseSchema($json);
        
        // Valide le sch�ma et d�tecte les erreurs �ventuelles
        $result=$dbs->validate();
        
        // S'il y a des erreurs, retourne un tableau JSON contenant la liste
        if ($result !== true)
        {
            header('Content-type: application/json; charset=iso-8859-1');
            echo json_encode(Utils::utf8Encode($result));
            return;
        }
        
        // Compile le sch�ma (attribution des ID, etc.)
        $dbs->compile();
        
        // Met � jour la date de derni�re modification (et de cr�ation �ventuellement)
        $dbs->setLastUpdate();
        
        // Aucune erreur : sauvegarde le sch�ma
        $dir='data/schemas/';
        $fabDir=Runtime::$fabRoot.$dir;
        $appDir=Runtime::$root.$dir;
            
        // R�cup�re les param�tres
        $template=Utils::get($_POST['template']);

        // V�rifie que le fichier existe
        if (!file_exists($appDir.$template))
            throw new Exception('Le fichier indiqu� n\'existe pas');
        
        // Enregistre le sch�ma
        file_put_contents($appDir.$template, $dbs->toXml());
        
        // Retourne l'url vers laquelle on redirige l'utilisateur
        header('Content-type: application/json; charset=iso-8859-1');
        echo json_encode('EditSchema');
    }


    public function actionAscoLoad()
    {
        while (ob_get_level()) ob_end_flush();

        set_time_limit(0);

        // cr�e la base
        echo 'Cr�ation de la base xapian dans ', DB_PATH, '<br />';
//        $xapianDb=Database::open(DB_PATH, false);

        $dbs=new DatabaseSchema(file_get_contents(Runtime::$root . 'data/schemas/ascodocpsy.xml'));
        $xapianDb=Database::create(DB_PATH, $dbs, 'xapian');
        
        
        // Importe des notices de la base bis dans la base xapian
        echo 'Ouverture de la base BIS : ', BIS_PATH, '<br />';
        $bisDb=Database::open(BIS_PATH, true, 'bis');

        echo 'Lancement d\'une recherche * dans la base BIS<br />';
        if (!$bisDb->search('*', array('_sort'=>'+','_start'=>1,'_max'=>-1)))
            die('aucune r�ponse');

        echo '<hr />';
        echo $bisDb->count(), ' notices � charger � partir de la base BIS<br />';
        echo '<hr />';
        
        while(ob_get_level()) ob_end_flush();
        echo '<pre>';
        echo 'nb total de notices charg�es; secondes depuis le d�but; secondes depuis pr�c�dent; nb de notices par seconde ; memory_usage ; memory_usage(true); memory_peak_usage ; memory_peak_usage(true)<br />';
        
        $nb=0;
        $startTime=$time=microtime(true);
        $nbRef=0;        
        foreach($bisDb as $record)
        {
            if ($nb %100 == 0)
            {
                $lastTime=$time;
                $time=microtime(true);
                echo sprintf
                (
                    '%6d ; %8.2f ; %6.2f ; %6.2f ; %10d; %10d ; %10d ; %10d<br />', 
                    $nb,
                    $time-$startTime,
                    $time-$lastTime,
                    $nb==0 ? 0.0: 100/($time-$lastTime),
                    memory_get_usage(),
                    memory_get_usage(true),
                    memory_get_peak_usage(),
                    memory_get_peak_usage(true)
                    
                );
                flush();
            }

            $xapianDb->addRecord();
            
            foreach($record as $name=>$value)
            {
                if ($name=='FinSaisie' || $name=='Valide' || $name=='REF') continue;
                
                if ($value=='') $value=null;
                
                if (! is_null($value) && in_array($name, array('Aut','Edit','Lieu','MotCle','Nomp','CanDes','EtatCol', 'Loc','ProdFich')))
                {
                    $value=array_map("trim",explode('/',$value));
                    if (count($value)===1) $value=$value[0];
                }
                
                $xapianDb[$name]=$value;
            }
            
            // Renseigne REF � l'aide du compteur.
            // On obtient ainsi un REF et un doc_id �gaux
            $nbRef++;
            //$xapianDb['REF']=$nbRef;
            
            // Remplace les 2 champs FinSaisie et Valide par le champ Statut
            // FinSaisie=0 et Valide=0 : Statut=encours
            // FinSaisie=1 et Valide=0 : Statut=avalider
            // Valide=1 : Statut=valide 
            if ($record['Valide']==1)
                $xapianDb['Statut']='valide';
            else
                $xapianDb['Statut']=($record['FinSaisie']==1) ? 'avalider' : 'encours';
            
            // Initialise LienAnne, Doublon, LastAuthor
            $xapianDb['LienAnne']=$xapianDb['LastAuthor']=null;
            $xapianDb['Doublon']=false;

            $xapianDb->saveRecord();
            $nb++;
//            if ($nb>=10) break;
        }

        // infos du dernier lot charg�
        $lastTime=$time;
        $time=microtime(true);
        echo sprintf
        (
            '%6d ; %8.2f ; %6.2f ; %6.2f<br />', 
            $nb,
            $time-$startTime,
            $time-$lastTime,
            $nb==0 ? 0.0: 100/($time-$lastTime)
        );
        flush();
        
        echo 'Fermeture (et flush) de la base<br />';
        unset($bisDb);
        unset($xapianDb);

        // pour mesurer le temps de fermeture
        $lastTime=$time;
        $time=microtime(true);
        echo sprintf
        (
            '%6d ; %8.2f ; %6.2f ; %6.2f<br />', 
            $nb,
            $time-$startTime,
            $time-$lastTime,
            0
        );
        flush();
        
        echo 'Termin�<br />';
    }
    public function actionDeleteAllRecords()
    {
        set_time_limit(0);
        $xapianDb=Database::open(DB_PATH, false, 'xapian');
        $xapianDb->deleteAllRecords();
        echo 'done';
    }
    
    /**
     * Lance une r�indexation compl�te de la base de donn�es dont le 
     * nom est pass� en param�tre.
     * 
     * Dans un premier temps, on affiche une page � l'utilisateur lui indiquant 
     * comment fonctionne la r�indexation et lui demandant de confirmer son
     * choix.
     * 
     * Dans un second temps (une fois qu'on a la confirmation), on cr�e une 
     * t�che au sein du gestionnaire de t�ches.
     * 
     * Enfin, la r�indexation a proprement parler est ex�cut�e par le 
     * {@link TaskManager}.
     *
     * @param string $database le nom de la base � r�indexer
     * @param boolean $confirm le flag de confirmation
     */
    public function NUactionReindex($database, $confirm=false)
    {
        // Si on est en ligne de commande, lance la r�indexation proprement dite
        if (User::hasAccess('cli'))
        {
            // Ouvre la base en �criture (pour la verrouiller)
            $this->selection=Database::open($database, false);
            
            // Lance la r�indexation
            $this->selection->reindex();
            return;
        }
        
        // Sinon, interface web : demande confirmation et cr�e la t�che
        
        // Ouvre la base et v�rifie qu'elle contient des notices
        $this->selection=Database::open($database, true);
        $this->selection->search(null, array('_max'=>-1, '_sort'=>'+'));
        if ($this->selection->count()==0)
        {
            echo '<p>La base ne contient aucun document, il est inutile de lancer la r�indexation.</p>';
            return;
        }
        
        // Demande confirmation � l'utilisateur
        if (!$confirm)
        {
            Template::run
            (
                config::get('template'),
                array('database'=>$database)
            );
            return;
        }

        // Cr�e une t�che au sein du gestionnaire de t�ches
        $id=Task::create()
            ->setRequest($this->request)
            ->setTime(0)
            ->setLabel('R�indexation compl�te de la base ' . $database)
            ->setStatus(Task::Waiting)
            ->save()
            ->getId();
            
        Runtime::redirect('/TaskManager/TaskStatus?id='.$id);
    }
    
    public function actionBisToXapian()
    {
        $bisDb=Database::open('ascodocpsy', true);
                
        if (!$bisDb->search('Type=rapport', array('_sort'=>'+','_start'=>1,'_max'=>100)))
            die('aucune r�ponse');
            
        $xapianDb=Database::open(DB_PATH, false, 'xapian');

        foreach($bisDb as $record)
        {
            $xapianDb->addRecord();
            foreach($record as $name=>$value)
            {
                if ($value) echo $name, ' : ', $value, "<br />";
                $xapianDb[$name]=$value;
            }
            $xapianDb->saveRecord();
            echo '<hr />';
        }
        die('ok');
    }
    
    public function actionDumpTerms()
    {
        $db=Database::open(DB_PATH, true, 'xapian');
        $prefix=$_SERVER['QUERY_STRING'];
        $db->dumpTerms($prefix);
    	
    }
}
?>