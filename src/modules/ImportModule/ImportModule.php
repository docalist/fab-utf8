<?php

/**
 * @package     fab
 * @subpackage  modules
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @author      S�verine Ferron <Severine.Ferron@bdsp.tm.fr>
 * @version     SVN: $Id: ImportModule.php 1200 2010-09-07 13:11:53Z daniel.menard.bdsp $
 */


/**
 * Module standard d'import
 *
 * @package     fab
 * @subpackage  modules
 */
class ImportModule extends DatabaseModule
{
    public function preExecute()
    {
        // Filtre sur les fichiers qui n'ont pas encore �t� import�s
        if (!$this->request->bool('done')->defaults(false)->ok())
            $this->request->add('_filter', 'NOT status:(import_*)');
    }

    public function actionDelete($confirm=0)
    {
        // todo: not dry, copier/coller int�gral de ce qu'on a dans DatabaseModule
        // si on avait les �v�nements dans fab, on pourrait juste avoir un event "onBeforeDelete, unlink($record[path])"

        // Ouvre la base de donn�es
        $this->openDatabase(false);

        // R�cup�re l'�quation de recherche qui donne les enregistrements � supprimer
        $this->equation=$this->getEquation();

        // Param�tre equation manquant
        if (is_null($this->equation))
            return $this->showError('Le ou les num�ros des notices � supprimer n\'ont pas �t� indiqu�s.');

        // Aucune r�ponse
        if (! $this->select($this->equation, -1) )
            return $this->showError("Aucune r�ponse. Equation : $this->equation");

        // TODO: d�l�guer au TaskManager

        // Supprime toutes les notices de la s�lection
        foreach($this->selection as $record)
        {
            $path=$this->selection['Path'];
            if ($path !=='' && !is_null($path))
            {
                echo 'suppression de ', $this->selection['Path'], '<br />';
                if (!@unlink($this->selection['Path']))   // seule diff�rence par rapport au actionDelete de DatabaseModule
                    echo 'erreur durant la suppression du fichier tmp';
            }
            $this->selection->deleteRecord();
        }

        // D�termine le template � utiliser
        if (! $template=$this->getTemplate())
        {
            echo '<p>Notice supprim�e.</p>';
            return;
        }

        // D�termine le callback � utiliser
        $callback=$this->getCallback();

        // Ex�cute le template
        Template::run
        (
            $template,
            array($this, $callback),
            $this->selection->record  //fixme : pas de sens : on passe un record supprim� + il peut y en avoir plusieurs
        );
    }

    /**
     * Cr�e une nouvelle t�che dans le gestionnaire de t�ches pour importer les
     * fichiers dont les num�ros de REF sont pass�s en param�tre
     *
     * @param array $REF les num�ros de REF des fichiers � importer. Seuls les
     * fichiers en statut 'upload_ok' seront accept�s (une exception sera
     * g�n�r�e dans le cas contraire).
     */
    public function actionNewImport(array $REF, $now, $date, $time)
    {
        // V�rifie les param�tres
        $now=$this->request->bool('now')->defaults(false)->ok();
        // todo: v�rifier les autres, v�rifier le format de date et time

        // D�termine l'heure d'ex�cution
        if ($now)
        {
            $time=0;
        }
        else
        {
            $time=mktime // hour min sec month day year
            (
                substr($time,0,2), substr($time,2,2), substr($time, 4, 2),
                substr($date,4,2), substr($date,6,2), substr($date, 0, 4)
            );
            if ($time < time())
            {
                echo "<p>Erreur : l'heure indiqu�e est d�j� d�pass�e.</p>";
                return;
            }
        }
        // echo strftime('%d/%m/%Y %H:%M:%S', $time);

        // Ouvre la base de donn�es en �criture
        $this->openDatabase(false);
//        echo 'Base ouverte en �criture<br />';

        // Cr�e une �quation � partir des num�ros de REF indiqu�s
        $equation='Status:upload_ok AND REF:(' . implode(' ', $REF) . ')'; // �quation du style REF:(5 8 12)
//        echo 'Equation de recherche : <code>', $equation, '</code><br />';

        // Recherche les Refs indiqu�es (max=toutes, ordre des enregs dans la base)
        $this->selection->search($equation, array('max'=>-1, 'sort'=>'+'));
//        echo 'Nombre de r�ponses : ', $this->selection->count(), '<br />';

        // Si on a moins de r�ponses que de REF, c'est qu'au moins une des REF n'�tait pas valide
        if ($this->selection->count() !== count($REF))
            throw new Exception('Certains des num�ros indiqu�s ne sont pas valides');

//        echo 'Num�ros de REF OK, cr�ation de la t�che<br />';

        if ((count($REF)===1))
            $title='Import d\'un fichier dans la base';
        else
            $title='Import de '.count($REF).' fichiers dans la base';

        // Cr�e la t�che maintenant pour r�cup�rer son ID
        $task=new Task();
        $taskId=$task->save()->getId();

//        echo 'taskId : ', $taskId, '<br />';

        // Pour chaque enreg, stocke le num�ro de t�che et met � jour le statut
//        echo 'Mise � jour du statut des enregs<br />';
        foreach($this->selection as $record)
        {
            $this->selection->editRecord();
            $record['Status']='task';
            $record['TaskId']=$taskId;
            $this->selection->saveRecord();
            echo 'Enreg REF=', $record['REF'], ' modifi�<br />';
        }

        if ($time!==0 && $time<time()) $time=time(); // au cas o� la mise � jour de la base ait pris beaucoup de temps

        // Initialise les param�tres de la t�che
        $task
            ->setRequest($this->request->setAction('Import')->keepOnly('REF'))
            ->setTime($time)
            ->setLabel($title)
            ->setStatus(Task::Waiting)
            ->save();

        if ($time===0)
            Runtime::redirect('/TaskManager/TaskStatus?id='.$taskId);
        else
            Runtime::redirect('/TaskManager/Index');
            //Runtime::redirect('/TaskManager/Index?_equation=id:'.$taskId);
    }

    /**
     * R�alise l'import des fichiers dont les num�ros de REF sont pass�s en
     * param�tres.
     *
     * Appell� par le TaskManager lorsque la t�che est lanc�e.
     *
     * @param array $REF num�ros de r�f�rence des fichiers � importer
     */
    public function actionImport(array $REF)
    {
        // Ouvre la base de donn�es en �criture
        $this->openDatabase(false);

        // Affiche le titre
        $nbFiles=count($REF);
        if ($nbFiles===1)
            echo '<h2>Import d\'un fichier dans la base (ref ', $REF[0], ')</h2>';
        else
            echo '<h2>Import de ', $nbFiles, ' fichiers dans la base</h2>';

        // Initialise les num�ros de premi�re et derni�re notice
        $firstRef=$lastRef=0;

        foreach($REF as $i=>$REF)
        {
            if ($nbFiles>1)
                echo '<h3>Fichier n�', $i+1, ' (ref ', $REF, ')</h3>';

            // Cr�e l'�quation de recherche
            // On filtre sur les fichiers autoris�s � �tre import�s
            $equation='Status:task AND REF:'.$REF;

            // Ouvre la base de donn�es en �criture
//            $this->openDatabase(false);
//            echo '1�re ouverture de la base en �criture<br />';

            // Recherche la fiche du fichier � importer
            if (! $this->selection->search($equation, array('max'=>-1, 'sort'=>'+')))
            {
                echo "<p style='color:red; font-weight: bold;'>Num�ro de r�f�rence invalide : $REF (le fichier correspondant n'existe pas)</p>";
                //unset($this->selection);
                continue;
            }

            // R�cup�re le path et le nom du fichier � importer
            $path=$this->selection['Path'];
            $fileName=$this->selection['FileName'];

            echo '<p>D�but de l\'import du fichier ', $fileName, ' le ', strftime('%d/%m/%Y � %H:%M:%S'), '</p>';

            // Met � jour le statut du fichier
            $this->selection->editRecord();
            $this->selection['Status']='import_running';
            $this->selection->saveRecord();

            // Ferme la base
//            unset($this->selection);
//            echo '1�re fermeture de la base<br />';

            // D�termine le callback � utiliser pour l'import des fichiers
            $callback=$this->callback(Config::get('importcallback'));

            // Aucun callback, l'import ne peut pas se faire
            if (is_null($callback))
            {
                $ok=false;
                $msg='Import du fichier impossible : Erreur interne : le callback d\'import n\'a pas �t� d�fini en configuration';
                echo '<p>', $msg, '</p>';
            }

            // Import du fichier
            else
            {
                // D�termine le path du fichier dans lequel seront stock�es les notices erron�es
                // Ces fichiers sont stock�s dans le m�me r�pertoire que les fichiers � importer,
                // en �tant pr�fix�s par 'err'
                $errorFile=tempnam(Utils::makePath($this->selection->getPath(), 'files'),'err');

                // todo : revoir le param�tre $path quand selection['Path']
                // contiendra un path relatif
                list($ok,$msg,$first,$last)=call_user_func($callback,$path,$errorFile);
            }

            // on lui passe en param�tres :
            // - $selection['Path'] : le path du fichier � importer
            // - $errorFile : le path d'un fichier dans lequel il peut
            //   stocker les notices erronn�es (tmp_name('db/files', 'err'))
            // le callback retourne :
            // - le statut
            // - le contenu du champ Notes

            // - true ou null : ok, tout a bien march� (unlink $errorFile)
            // - false : ? �a n'a pas march�, mais on ne sait pas pourquoi
            // - string (ou array de string ?) : liste des erreurs rencontr�es

            // stocker dans Notes le message d'erreur + mention g�n�r�e par nous
            // (heure de d�but et de fin, dur�e...)
            // status=import_ok, import_warning ou import_error

            // Ouvre de nouveau la base de donn�es en �criture
//            $this->openDatabase(false);
//            echo '2e ouverture de la base en �criture<br />';

            // L'import s'est bien pass�
            if ($ok)
            {
                // Supprime le fichier des notices erron�es si toutes les notices ont �t� import�es
                unlink($errorFile);

                // R�cup�re les num�ros de la premi�re et derni�re notice
                $firstRef=$firstRef===0 ? $first : min($firstRef,$first);
                $lastRef=max($lastRef,$last);
            }

            // Met � jour le statut du fichier et renseigne le champ Notes
            if ($this->selection->search("REF=$REF", array('max'=>1)))
            {
                $this->selection->editRecord();
                $this->selection['Status']=($ok) ? 'import_ok' : 'import_error';
                $this->selection['Notes'].=$msg;
                $this->selection->saveRecord();
            }

            // Ferme la base
//            unset($this->selection);
//            echo '2e fermeture de la base<br />';

            $time=strftime('%d/%m/%Y � %H:%M:%S');
            echo '<p>Fin de l\'import le ', $time, '</p>';
        }

        // TODO : Cr�er une cl� de config 'dedouble' (true, false) pour dire si on
        // doit lancer le d�doublonnage automatiquement apr�s l'import

        // D�doublonnage sur les notices import�es
        if (! is_null($firstRef) && ! is_null($lastRef))
        {
            // Cr�e la requ�te
            $equation="REF:$firstRef";
            $equation.=$lastRef!==0 ? "..$lastRef" : '';
            $request=Request::create()->setModule('DedupModule')->setAction('Dedup')->set('_equation',$equation);

            // Titre de la t�che
            $label='D�doublonnage ';
            $label.=$nbFiles===1 ? 'du fichier int�gr�' : "des $nbFiles fichiers int�gr�s";
            $label.=' dans la base '.Config::get('database').' le '.$time;

            // Cr�e une t�che au sein du gestionnaire de t�ches
            $id=Task::create()
                ->setRequest($request)
                ->setTime(0)
                ->setLabel($label)
                ->setStatus(Task::Waiting)
                ->save()
                ->getId();

            // Propose un lien vers le r�sultat du d�doublonnage
            echo '<p><a href="', Routing::linkFor('/TaskManager/TaskStatus?id='.$id),'">Voir le r�sultat du d�doublonnage r�alis� sur les notices import�es</a></p>';
        }
    }

    public function actionUpload()
    {
        // Ouvre la base de donn�es en �criture
        $this->openDatabase(false);

        // D�termine le callback � utiliser pour la validation du fichier
        $callback=$this->callback(Config::userGet('validcallback'));

        // R�pertoire dans lequel vont �tre stock�s les fichiers upload�s
        $dir=Utils::makePath($this->selection->getPath(), 'files');

        foreach($_FILES as $file) // todo: on ne devrait pas utiliser $_FILES, request devrait avoir les m�thodes pour g�rer les fichiers
        {
            // D�termine un nom pour le fichier upload�
            // todo : $path doit �tre un chemin relatif par rapport au r�pertoire de la base : files/ficXXX.tmp
            $path=tempnam($dir, 'fic');

            // V�rifie que le fichier temporaire a �t� cr�� dans le bon r�pertoire
            // Permet de v�rifier que $dir existe, qu'on peut �crire dedans, etc.
            if (strpos($path,$dir)!==0)
                throw new Exception(sprintf('Impossible de cr�er un fichier dans le r�pertoire %s (erreur de configuration du serveur : v�rifiez l\'existence et les droits de ce r�pertoire)', $dir));

            // V�rifie et copie le fichier upload�
            $result=Utils::uploadFile($file, $path, $callback);

            switch(true)
            {
                case $result===true: // ok, on cr�e un enreg
                    $this->selection->addRecord();

                    $this->selection['Path']=$path;
                    $this->selection['FileName']=$file['name'];
                    $this->selection['Size']=$file['size'];
                    $this->selection['Status']='upload_ok';
                  //$this->selection['TaskId']=null;
                  //$this->selection['Notes']='';
                    $this->selection['Creation']=$this->selection['LastUpdate']=time();
                    $this->selection['Ident']=User::get('login');

                    $this->selection->saveRecord();
                    break;

                case $result===false: // aucun fichier
                    unlink($path);
                    break;

                default: // erreur
                    unlink($path);
//                    echo $result;

                    $this->selection->addRecord();

                    // todo : no dry

                  //$this->selection['Path']=null; // le fichier *n'a pas* �t� stock� puisqu'on a eu une erreur
                    $this->selection['FileName']=$file['name'];
                    $this->selection['Size']=$file['size'];
                    $this->selection['Status']='upload_error';
                  //$this->selection['TaskId']=null;
                    $this->selection['Notes']=$result;
                    $this->selection['Creation']=$this->selection['LastUpdate']=time();
                    $this->selection['Ident']=User::get('login');

                    $this->selection->saveRecord();
            }
        }

        Runtime::redirect('/'.$this->request->getModule().'/'); // todo: +anchor du premier ajout�
//        echo 'upload done';
    }


    /**
     * G�n�re un tableau d'horaires
     *
     * La fonction timeSteps g�n�re un tableau contenant tous les horaires
     * possibles entre l'heure de d�but et l'heure de fin indiqu�es en avan�ant
     * de $step minutes � chaque fois.
     *
     * Exemples :
     * <code>
     * timeSteps(23, 1, 30) // de 23h00 � 01h00 du matin par tranches de 30 minutes
     * -> '23:00', '23:30', '00:00', '00:30', '01:00'
     *
     * timeSteps(12, 13, 15) // de midi � treize heures par tranches d'un quart d'heure
     * -> '12:00', '12:15', '12:30', '12:45', '13:00'
     * </code>
     *
     * Remarques :
     * - si les param�tres indiqu�s sont en dehors de l'intervalle autoris�, ils
     *   seront ajust�s (par exemple si vous indiquez 25 comme heure de d�but,
     *   les horaires g�n�r�s commenceront � 01h00 du matin).
     * - les horaires g�n�r�s contiennent toujours les heures de d�but et de fin.
     * - si step vaut 0, seul l'horaire de d�but est g�n�r� :
     * <code>
     *     timeSteps(12, 12, 0) -> '12:00'
     * </code>
     * - si les horaires de d�but et de fin sont identiques, tous les horaires
     *   possibles sur 24 heures sont g�n�r�s :
     * <code>
     *     timeSteps(12, 12, 60) // de midi � midi d'heure en heure
     *     -> '12:00', '13:00', '14:00' ... '09:00', '10:00', '11:00'
     * </code>
     *
     * @param int $start l'heure de d�but (de 0 � 23)
     * @param int $end l'heure de d�but (de 0 � 23)
     * @param int $step le "pas" � appliquer, en minutes (de 0 � 60)
     * @param string $format le format � utiliser pour g�n�rer les valeurs du
     * tableau retourn�, tel que reconnu par la fonction
     * {@link http://php.net/strftime strftime()} de php.
     *
     * @return array un tableau de chaines contenant les diff�rents horaires
     * g�n�r�s.
     *
     * Les cl�s du tableau contiennent toujours l'horaire sous la forme
     * 'hhmmss' (i.e. heures minutes secondes sur deux chiffres) et les
     * valeurs associ�es contiennent le m�me horaire mais sous la forme indiqu�e
     * par $format.
     *
     * Par d�faut, $format stocke les valeurs avec le m�me format
     * que les cl�s, mais vous pouvez indiquer un format diff�rent si vous
     * souhaitez pr�senter les horaires autrement � l'utilisateur :
     *
     * Exemples :
     * <code>
     * // Format par d�faut : cl�s et valeurs sont identiques
     * timeSteps(22, 23, 30)
     * -> array('22:00'=>'22:00', '22:30'=>'22:30', '23:00'=>'23:00')
     *
     * // Utilise le format pr�f�r� de repr�sentation de l'heure (%X) :
     * timeSteps(22, 23, 30, '%X')
     * -> array('22:00'=>'22:00:00', '22:30'=>'22:30:00', '23:00'=>'23:00:00')
     *
     * // N'affiche que l'heure et un libell�
     * timeSteps(22, 23, 60, '%H heures')
     * -> array('22:00'=>'22 heures', '23:00'=>'23 heures')
     * </code>
     */
    public function timeSteps($start, $end, $step, $format='%H:%M')
    {
        // Si step vaut z�ro, on ne g�n�re que l'heure de d�but
        if ($step==0)
            return array(sprintf('%02d0000', $start)=>strftime($format, mktime($start, 0, 0)));

        // Ajuste start et end dans l'intervalle autoris�
        $start=$start % 24;
        if ($end<$start) $end+=24;

        // Calcule le nombre d'horaires � g�n�r� (z�ro = journ�e compl�te)
        $nb=($end-$start)%24;
        if ($nb==0) $nb=24;

        // G�n�re tous les horaires
        $t=array();
        for($i=0;$i<$nb;$i++)
        {
            for ($j=0; $j<60/$step; $j++)
            {
                $t[sprintf('%02d%02d00', $start, $j*$step)] =
                    strftime($format, mktime($start, $j*$step, 0));
            }
            $start++;
            if ($start>=24) $start-=24;
        }

        // Ajoute l'horaire de fin (sauf si on g�n�re une journ�e compl�te)
        if ($nb!= 24)
            $t[sprintf('%02d%02d00', $start, 0)]=strftime($format, mktime($start, 0, 0));

        // Termin�
        return $t;
    }

    /**
     * V�rifie et charge un callback.
     *
     * @param callback $callback le callback � v�rifier et � initialiser.
     * Il peut s'agir :
     * - d'une chaine de caract�res simple : dans ce cas, le callback doit �tre
     *   une fonction globale d�finie par php ou par l'application.
     * - une chaine de caract�res de la forme 'class::m�thode'
     * - une chaine de caract�res de la forme 'class->m�thode'
     * - une chaine de caract�res de la forme 'self::m�thode'
     * - une chaine de caract�res de la forme 'this->m�thode'
     *
     * un tableau de deux �l�ments contenant :
     * - (chaine,chaine) idem 'class::m�thode'
     * - ('self',chaine) 'self::m�thode'
     * - ('this',chaine) 'this->m�thode'
     * - (objet,chaine)  'class->m�thode'
     *
     * @throws BadCallbackException si le callback n'est pas valide
     *
     * @return callback le callback v�rifi� et modifi�
     */
    public final function callback($callback)
    {
        // Si on nous passe null, retourne null
        if (is_null($callback)) return null;

        // Si c'est une chaine, analyse et transforme �ventuellement en tableau
        if (is_string($callback))
        {
            if ($callback==='') return null;

            if (strpos($callback, '::'))
            {
                $callback=explode('::',$callback, 2);
                $static=true;
            }
            elseif (strpos($callback, '->'))
            {
                $callback=explode('->',$callback, 2);
                $static=false;
            }
            else // simple chaine, on traite le cas maintenant
            {
                if (is_callable($callback)) return $callback;
                throw new BadCallbackException($callback);
            }
        }

        // Tableau : v�rifie qu'on a deux �l�ments num�rot�s 0 et 1
        elseif (is_array($callback))
        {
            if ( count($callback)!==2 || !isset($callback[0]) || !isset($callback[1]) )
                throw new BadCallbackException($callback);

            if (is_string($callback[0]))
                $static=true;
            elseif(is_object($callback[0]))
                $static=false;
            else
                throw new BadCallbackException($callback);
        }

        // Autre chose : c'est une erreur
        else
        {
            throw new BadCallbackException($callback);
        }

        // arriv� l� on a forc�ment un tableau de la forme ('self'|'this'|'classe'|objet ; 'm�thode')
        if (is_string($class=$callback[0]))
        {
            if ($class==='self')
            {
                $callback[0]=get_class($this);
            }
            elseif ($class==='this')
            {
                $callback[0]=$this;
            }
            else
            {
                if (class_exists($class, true))
                {
                    if (! $static) // classe existe, appel dynamique, cr�e une instance du module
                    {
                        if (is_subclass_of($class, 'Module'))
                        {
                            $callback[0]=Module::loadModule($class);
                            Config::addArray($callback[0]->config);    // fixme: objectif : uniquement $this->config mais pb pour la config transversale (autoincludes...) en attendant : on recopie dans config g�n�rale
                        }
                        else
                            $callback[0]=new $class();
                    }
                }
                else // la classe n'existe pas, charge le source du module
                {
                    try
                    {
                        $object=Module::loadModule($class);
                        // optimisation : if $static, ne charger que le source, pas la config

                        if ($static)
                        {
                            $callback[0]=$class;
                        }
                        else
                        {
                            $callback[0]=$object;
                            Config::addArray($callback[0]->config);    // fixme: objectif : uniquement $this->config mais pb pour la config transversale (autoincludes...) en attendant : on recopie dans config g�n�rale
                        }

                    }
                    catch (ModuleNotFoundException $e)
                    {
                        throw new BadCallbackException($callback);
                    }
                    catch (Exception $e)
                    {
                        throw $e;
                    }
                }
            }
        }

        // V�rifie et retourne le callback obtenu
        if (is_callable($callback)) return $callback;
        throw new BadCallbackException($callback);

    }
}

class BadCallbackException extends Exception
{
    public function __construct($callback)
    {
        parent::__construct(sprintf('Callback invalide : %s', Utils::varExport($callback, true)));
    }
}
?>