<?php
/**
 * @package     fab
 * @subpackage  TaskManager
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Task.php 825 2008-06-26 16:09:02Z daniel.menard.bdsp $
 */

/**
 * Repr�sente une t�che qui sera ex�cut�e ult�rieurement par le 
 * {@link TaskManager gestionnaire de t�ches}.
 * 
 * Une t�che est principalement constitu�e {@link setRequest() d'une requ�te} 
 * qui sera ex�cut�e � une {@link setTime() heure donn�e} et qui 
 * �ventuellement {@link setRepat() se r�p�te}.
 * 
 * Elle comporte �galement diff�rents attributs ({@link setLabel() un titre}, 
 * {@link setStatus() un �tat}, ...) et diff�rentes informations de gestion
 * ({@link getId() identifiant unique}, {@link getCreation() date de cr�ation},
 * date de {@link getNext() prochaine} et de {@link getLast() derni�re} 
 * ex�cution, {@link getOutput() sortie g�n�r�e} lors de la derni�re ex�cution, 
 * etc.)
 * 
 * La classe Task est une interface agile permettant de cr�er et de modifier des 
 * t�ches.
 * 
 * La majorit� des m�thodes retourne la t�che en cours ce qui permet de 
 * chainer les m�thodes.
 * 
 * Exemple :
 * <code>
 *     $task=new Task();
 * 
 *     $task
 *         ->setRequest($this->request->setAction('doBackup'))  // Lancer une sauvegarde
 *         ->setTime(0)                                         // Ex�cuter "d�s que possible"
 *         ->setRepeat('1 j./lun-ven')                          // Puis tous les jours du lundi au vendredi 
 *         ->setLabel('Sauvegarde de la base')                  // Le titre de la t�che 
 *         ->save();
 *     
 *     echo 'La t�che ', $task->getId(), ' a �t� cr��e.'; 
 * </code> 
 * 
 * Assez souvent, la requ�te correspondant � la t�che � cr�er est similaire
 * (voire identique) � la requ�te appell�e pour cr�er la t�che (par exemple
 * on appelle une action 'backup' qui va v�rifier tous les param�tres fournis 
 * puis qui va cr�er une t�che 'doBackup' en lui passant tous ces les param�tres).
 * 
 * N�anmoins, vous pouvez modifier comme vous le souhaitez la requ�te en cours
 * (comme dans l'exemple ci-dessus ou on a simplement chang� l'action � ex�cuter)
 * ou cr�er un nouvel {@link Request objet Request} de toute pi�ce.
 * 
 * Exemple :
 * <code>
 *     // Cr�e d'abord la requ�te  
 *     $request=new Request();
 *     $request
 *         ->setModule('Backup')
 *         ->setAction('backupDatabase')
 *         ->set('database', 'mydb');
 *         ->set('format', 'xml');
 *         ->set('filename', 'backup.xml');
 *
 *     // Puis planifie la t�che 
 *     $task=new Task();
 *     $task
 *         ->setRequest($request)   // Lancer une sauvegarde
 *         ->setTime(time()+86400)  // demain
 *         ->save();
 * </code> 
 * 
 * @package     fab
 * @subpackage  TaskManager
 */
class Task
{
    /**
     * Constantes repr�sentant le statut des t�ches (cf {@link getStatus()})
     */
    const Disabled='disabled';  // t�che d�sactiv�e
    const Waiting='waiting';    // t�che en attente (ce n'est pas encore l'heure de l'ex�cuter)
    const Starting='starting';  // t�che en train de d�marrer (lanc�e par le TaskManager)
    const Running='running';    // t�che en cours d'ex�cution
    const Done='done';          // t�che termin�e
    const Error='error';        // t�che en erreur (lancement impossible, erreur durant l'ex�cution...)
    const Expired='expired';    // heure d'ex�cution pr�vue d�pass�e
    
    /**
     * Num�ro unique identifiant la t�che
     * L'id n'existe que pour une t�che qui a �t� {@link save() enregistr�e}
     * 
     * @var null|int
     */
    private $id=null;

    /**
     * Libell� de la t�che
     * 
     * @var string
     */
    private $label='';
    
    /**
     * Statut de la t�che
     * 
     * @var string
     */
    private $status=self::Waiting;
    
    /**
     * Path complet du r�pertoire racine de l'application propri�taire de la 
     * t�che.
     * 
     * Correspond � la valeur de {@link Runtime::$root} au moment o� la t�che 
     * est cr��e.
     * 
     * @var path
     */
    private $applicationRoot='';
    
    /**
     * Url compl�te du front controler utilis� pour cr�er la t�che
     *
     * @var string
     */
    private $url='';
    
    /**
     * Date/heure � laquelle la t�che a �t� cr��e
     * 
     * @var timestamp
     */
    private $creation=null;
    
    /**
     * Date/heure � laquelle la t�che est planifi�e
     * (null = jamais, 0=d�s que possible)
     * 
     * @var null|0|timestamp
     */
    private $time=null;
    
    /**
     * Information de r�p�tition de la t�che
     * (null = ne pas r�p�ter)
     * 
     * @var string
     */
    private $repeat=null;

    /**
     * Module � charger pour ex�cuter la t�che
     * 
     * @var string
     */
    private $module=null;
    
    /**
     * Action � appeler pour ex�cuter la t�che
     * 
     * @var string
     */
    private $action=null;
    
    /**
     * Param�tres � passer � l'action pour ex�cuter la t�che
     * 
     * @var string
     */
    private $parameters=null;
    
    /**
     * Date/heure de la prochaine ex�cution de la t�che
     * 
     * @var null|0|timestamp
     */
    private $next=null;

    /**
     * Date/heure de la derni�re ex�cution de la t�che
     * 
     * @var null|timestamp
     */
    private $last=null;
    
    /**
     * Path du fichier contenant la sortie g�n�r�e par la t�che
     * 
     * @var path
     */
    private $output=null;
    
    
    /**
     * Cr�e une nouvelle t�che ou charge une t�che existante.
     * 
     * Si aucun ID de t�che n'est indiqu�, une nouvelle t�che est cr��e. Dans 
     * le cas contraire l'ID indiqu� et recherch� dans la base et la t�che 
     * correspondante est charg�e.
     * 
     * La t�che est cr��e/charg�e uniquement en m�moire : les modifications 
     * apport�es ne seront enregistr�es dans la base de donn�es que lorsque 
     * {@link save()} sera appel�e.
     * 
     * En interne, il est �galement possible de cr�er une t�che en passant en
     * param�tre un objet {@link Database} ouvert sur l'enregistrement de t�che 
     * � charger.
     * 
     * @param null|int|Database $id l'identifiant de la t�che � charger ou null 
     * pour cr�er une nouvelle t�che ou une s�lection pour cr�er une t�che � 
     * partir de l'enregistrement en cours.
     * 
     * @throws Exception si l'identifiant indiqu� n'est pas correct 
     */
    public function __construct($id=null)
    {
        // Cr�e une nouvelle t�che (em m�moire, sera r�ellement cr��e si save est appel�e)
        if (is_null($id))
        {
            $this->creation=self::timestampToString(time());
            $this->applicationRoot=Runtime::$root;
            $this->status=self::Disabled;
            $this->url=Utils::getHost() . Runtime::$realHome . Runtime::$fcName;
        }
        
        // Charge la t�che indiqu�e
        else
        {
            if ($id instanceof Database)
            {
                $tasks=$id;
            }
            else
            {
                // Ouvre la base de donn�es (readonly)
                $database=TaskManager::getDatabasePath();
                $tasks=Database::open($database, true, 'xapian');
                
                // Recherche la t�che indiqu�e
                if( !$tasks->search('ID='.$id))
                    throw new Exception(sprintf('La t�che %s n\'existe pas',$id));
            }
            
            // Charge toutes les propri�t�s
            foreach($this as $property=>&$value)
                $value=$tasks[$property];
            
            // Ferme la base
            unset($tasks);
        }
    }

    /**
     * M�thode statique permettant de cr�er une nouvelle t�che ou de charger 
     * une t�che existante.
     * 
     * Php ne permet pas d'appeller une m�thode sur un objet juste cr�� avec
     * l'op�rateur new (<code>new Task()->save()</code>). Cette m�thode 
     * contourne en permettant d'�crire <code>Task::create()->save()</code>.
     *
     * @param null|int|Database $id l'identifiant de la t�che � charger ou null 
     * pour cr�er une nouvelle t�che ou une s�lection pour cr�er une t�che � 
     * partir de l'enregistrement en cours.
     * 
     * @throws Exception si l'identifiant indiqu� n'est pas correct
     *  
     * @return Task
     */
    public static function create($id=null)
    {
        return new Task($id);
    }
    
    /**
     * Enregistre la t�che.
     *
     * S'il s'agit d'une nouvelle t�che, un nouvel enregistrement est cr��
     * dans la base de donn�es et un {@link getId() identifiant unique} est 
     * attribu� � la t�che, sinon les modifications apport�es � la t�che sont
     * enregistr�es.
     * 
     * Lors de l'enregistrement, la {@link getNext() date/heure de prochaine 
     * ex�cution} de la t�che est mise � jour.
     * 
     * Si celle-ci est correcte, un signal est envoy� au {@link TaskManager
     * gestionnaire de t�ches} pour qu'il tienne compte des modifications 
     * apport�es � la t�che.
     * 
     * Remarque :
     * Si vous avez besoin tr�s t�t de l'identifiant de la t�che (par exemple
     * pour indiquer dans des fichiers l'id de la t�che associ�e) vous pouvez
     * appeler {@link save()} juste apr�s avoir cr�� une t�che.
     * 
     * Exemple :
     * <code>
     *     // On cr�e la t�che
     *     $task=new Task();
     * 
     *     // Et on l'enregistre aussit�t pour qu'un ID soit allou�
     *     $task->save(); 
     * 
     *     // On r�cup�re l'ID obtenu et on fait quelque chose avec
     *     $id=$task->getId();
     *     // ...
     * 
     *     // On termine le param�trage de la t�che et l'enregistre � nouveau
     *     $task->setRequest($request)->setTime(0)->save();
     * </code>
     * 
     * @return Task $this pour permettre le chainage des appels de m�thodes
     * 
     * @throws Exception Si la t�che en cours n'existe pas (c'est un cas rare
     * qui peut subvenir si la t�che est supprim�e via l'interface du 
     * TaskManager entre le moment o� la t�che est charg�e et le moment ou elle 
     * est enregistr�e).
     */
    public function save()
    {
        // Ouvre la base de donn�es (read/write)
        $database=TaskManager::getDatabasePath();
        $tasks=Database::open($database, false, 'xapian');
        
        // Nouvelle t�che jamais enregistr�e (ie n'a pas d'ID) : addRecord
        if (is_null($this->id))
        {
            $tasks->addRecord();
        }
        
        // Sinon : updateRecord
        else
        {
            if( !$tasks->search('ID='.$this->id))
                throw new Exception(sprintf('Erreur interne : la t�che %s n\'existe pas', $this->id));
            $tasks->editRecord();
        }
        
        $this->next=self::timestampToString($this->computeNext());
        
        //$signal=(($this->status===Task::Waiting) && ($this->next !== $tasks['next']));
        $signal=($this->status===Task::Waiting);
        
        // Copie toutes nos propri�t�s dans l'enreg
        foreach($this as $property=>$value)
            $tasks[$property]=$value;
        
        // Enregistre la t�che
        $tasks->saveRecord();
        
        // Recopie dans l'autre sens (ie : r�cup�re l'ID, lastUpdate �ventuel...)
        foreach($this as $property=>&$value)
            $value=$tasks[$property];
            
        // Ferme la base
        unset($tasks);
        
        if ($signal)
        {
            TaskManager::daemonUpdate();
        }
        return $this;
    }
    
    /**
     * Retourne le path du r�pertoire racine de l'application qui a cr�� cette 
     * t�che.
     * 
     * i.e. � quel site appartient cette t�che
     *
     * @return path
     */
    public function getApplicationRoot()
    {
        return $this->applicationRoot;
    }

    /**
     * Retourne l'url de la page d'accueil de l'application qui a cr�� cette 
     * t�che.
     *
     * @return path
     */
    public function getUrl()
    {
        return $this->url;
    }
    
    /**
     * D�finit la requ�te � ex�cuter pour lancer cette t�che
     *
     * @param Request $request
     * 
     * @return Task $this pour permettre le chainage des appels de m�thodes
     */
    public function setRequest(Request $request)
    {
        $this->module=$request->getModule();
        $this->action=$request->getAction();
        $this->parameters=$request->getParameters();
        return $this;    
    }
    
    /**
     * Retourne la requ�te qui sera ex�cut�e lorsque la t�che sera lanc�e
     *
     * @return Request
     */
    public function getRequest()
    {
        $request=new Request(is_null($this->parameters) ? array() : $this->parameters);
        return $request->setModule($this->module)->setAction($this->action);    
    }
/*    
    public function getModule()
    {
        return $this->module;
    }

    public function setModule($module)
    {
        $this->module=$module;
        return $this;
    }
    
  
A REMPLACER PAR :

    setRequest(Request $request)
    getRequest() : Request
---
 
    public function getAction()
    {
        return $this->action;
    }

    public function setAction($action)
    {
        $this->action=$action;
        return $this;
    }
    
    public function getParameters()
    {
        return $this->parameters;
        
//        if (is_null($this->parameters)) return null;
//        return unserialize($this->parameters);
    }

    public function setParameters($parameters)
    {
        $this->parameters=$parameters;
//        if (is_null($parameters))
//            $this->parameters=null;
//        else
//            $this->parameters=serialize($parameters);
        return $this;
    }
*/    
    /**
     * Retourne l'identifiant unique de la t�che
     *
     * @param bool $crypted par d�faut, la fonction retourne un ID crypt�,
     * indiquer false pour avoir l'ID r�el.
     * 
     * todo: voir si on garde, expliquer le but
     * 
     * @return null|int|string
     */
    public function getId($crypted=true)
    {
        if ($crypted) return $this->id; // todo: cryptage/d�cryptage
        return $this->id;
    }
    
    /**
     * Retourne la date/heure � laquelle l'ex�cution de la t�che est pr�vue.
     * 
     * @return false|null|0|timestamp l'heure d'ex�cution pr�vue pour la t�che :
     * - null : t�che non planifi�e, ne sera jamais ex�cut�e
     * - 0 : ex�cuter la t�che d�s que possible
     * - timestamp : un entier indiquant l'heure d'ex�cution pr�vue
     */
    public function getTime()
    {
        return self::stringToTimestamp($this->time);
    }

    
    /**
     * Modifie la date/heure � laquelle l'ex�cution de la t�che est pr�vue.
     * 
     * @param null|0|timestamp $time l'heure d'ex�cution pr�vue pour la t�che.
     * 
     * Les valeurs autoris�es sont :
     * - null : t�che non planifi�e, ne sera jamais ex�cut�e
     * - 0 : ex�cuter la t�che d�s que possible
     * - timestamp : un entier indiquant l'heure d'ex�cution pr�vue
     * 
     * @return Task $this pour permettre le chainage des appels de m�thodes
     */
    public function setTime($time)
    {
        $this->time=self::timestampToString($time);
        return $this;
    }
    
    /**
     * Indique si la t�che est r�currente ou non et, si elle l'est, la mani�re
     * dont elle sera r�p�t�e. 
     *
     * @return null|string la fonction retourne null si la t�che n'est pas 
     * r�currente ou une chaine de caract�res indiquant les informations de 
     * r�p�tition sinon.
     * 
     * Voir la fonction {@link setRepeat()} pour le format de la chaine 
     * retourn�e.
     */
    public function getRepeat()
    {
        return $this->repeat;
    }
    
    /**
     * D�finit la mani�re dont une t�che r�currente sera r�p�t�e.
     * 
     * Par d�faut, une t�che nouvellement cr��e ne s'ex�cutera qu'une seule fois
     * mais il est possible de d�finir une t�che r�currente (faire une 
     * sauvegarde toutes les nuits, nettoyer les fichiers temporaires toutes les
     * 12 heures...) en appelant setRepeat() avec une chaine indiquant comment
     * la t�che doit se r�p�ter.
     * 
     * La chaine se compose de deux parties :
     * - un nombre indiquant la fr�quence � laquelle la t�che sera r�p�t�e
     * (toutes les heures, tous les 2 jours, tous les mois...)
     * - un filtre optionnel indiquant des restrictions sur les dates autoris�es
     * (seulement la nuit, uniquement en semaine...)
     * 
     * La fr�quence de r�p�tition doit �tre exprim�e sous la forme d'un entier 
     * (positif et non nul) suivi d'une unit� de temps (par exemple '10 min.'
     * pour une t�che � ex�cuter toutes les dix minutes).
     * 
     * Les unit�s de temps accept�es sont :
     * - secondes : 's', 'sec', 'second', 'seconde' 
     * - minutes : 'mn', 'min', 'minute' 
     * - heures : 'h', 'hour', 'heure'
     * - jours : 'd', 'j', 'day', 'jour'
     * - mois : 'mon', 'month', 'monthe', 'moi'
     *
     * L'unit� peut �tre ou non suivie d'un point ou d'un 's' final
     * ('10 min' == '10 min.', '10 minutes' == '10 minutes').
     * 
     * Remarque :
     * Il n'existe pas d'unit�s pour indiquer 'une ann�e' ou 'un trimestre' : 
     * vous devez indiquer la dur�e en mois.
     * 
     * Le filtre, s'il est pr�sent, doit �tre constitu� d'une suite d'�l�ments
     * exprim�s dans l'unit� de temps indiqu�e avant. Chaque �l�ment peut �tre
     * un �l�ment unique ou une p�riode indiqu�e par deux �l�ments s�par�s par
     * un tiret.
     * 
     * Exemples :
     * - "1 mois" : t�che ex�cut�e tous les mois.
     * - "1 h./8-12,14-18" : toutes les heures, mais seulement de 8h � 12h et de
     *   14h � 18h
     * - "1 jour/lun-mar" : tous les jours, mais seulement les jours en semaine
     * - "2 jours/1-15,lun-mar,ven" : tous les deux jours, mais seulement si �a
     * tombe sur un jour compris entre le 1er et le 15 du mois ou alors si le
     * jour obtenu est un lundi, un mardi ou un vendredi
     * - "1 jour/sam" : tous les samedis
     * 
     * Remarques : les espaces sont autoris�s un peu partout (entre le nombre
     * et l'unit�, entre les �l�ments des filtres, etc.)
     * 
     * @param null|string $repeat soit une chaine indiquant la mani�re dont la 
     * t�che doit �tre r�p�t�e, soit null pour indiquer que la t�che n'est pas 
     * r�currente.
     * 
     * @return Task $this pour permettre le chainage des appels de m�thodes
     */
    public function setRepeat($repeat=null)
    {
        if ($repeat===false || $repeat===0 || $repeat==='') $repeat=null;
        $this->repeat=$repeat;
        return $this;
    }

    /**
     * Retourne la date de derni�re ex�cution de la t�che ou null si la t�che 
     * n'a pas encore �t� lanc�e.
     * 
     * @return timestamp|null un entier repr�sentant la date/heure de derni�re
     * ex�cution de la t�che ou null
     */
    public function getLast()
    {
        return self::stringToTimestamp($this->last);
    }
    
    /**
     * Modifie la date de derni�re ex�cution de la t�che.
     *
     * @param timestamp|null $last un entier repr�sentant la date/heure de
     * derni�re ex�cution ou null si la t�che pour indiquer que la t�che n'a 
     * pas encore �t� lanc�e.
     * 
     * @return Task $this pour permettre le chainage des appels de m�thodes
     */
    public function setLast($last)
    {
        $this->last=self::timestampToString($last);
        return $this;
    }
    
    /**
     * Retourne la date � laquelle la t�che a �t� cr��e.
     *
     * @return timestamp un entier repr�sentant la date/heure � laquelle la 
     * t�che a �t� cr��e.
     */
    public function getCreation()
    {
        return self::stringToTimestamp($this->creation);
    }

    /**
     * Retourne la date de prochaine ex�cution pr�vue pour la t�che.
     * 
     * todo: pas clair, expliquer la diff�rence entre {@link getTime} et getNext() 
     * 
     * @return null|0|timestamp un entier repr�sentant l'heure d'ex�cution 
     * pr�vue pour la t�che :
     * - null : t�che non planifi�e, ne sera jamais ex�cut�e
     * - 0 : ex�cuter la t�che d�s que possible
     * - timestamp : un entier indiquant l'heure d'ex�cution pr�vue
     * 
     */
    public function getNext()
    {
        return self::stringToTimestamp($this->next);
    }

    /**
     * Retourne le statut actuel de la t�che
     *
     * @return string l'une des constantes de statut d�finies dans la 
     * classe Task :
     * {@link Waiting}, {@link Starting}, {@link Running}, {@link Done},
     * {@link Error}, {@link Disabled} et {@link Expired}
     */
    public function getStatus()
    {
        return $this->status;
    }
    
    /**
     * Modifie le statut de la t�che
     *
     * @param string $status l'une des constantes de statut d�finies dans la 
     * classe Task :
     * {@link Waiting}, {@link Starting}, {@link Running}, {@link Done},
     * {@link Error}, {@link Disabled} et {@link Expired}
     *   
     * @return Task $this pour permettre le chainage des appels de m�thodes
     * 
     * @throws OutOfRangeException si le statut indiqu� n'est pas valide 
     */
    public function setStatus($status)
    {
        switch ($status)
        {
            case self::Waiting:
            case self::Starting:
            case self::Running:
            case self::Done:
            case self::Error:
            case self::Disabled:
            case self::Expired:
                $this->status=$status;
                break;
            default:
                throw new OutOfRangeException
                (
                    sprintf('Statut de t�che invalide : %s', $status)
                );
        }
        return $this;
    }
    
    /**
     * Retourne le libell� (le titre) de la t�che
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }
    
    /**
     * Modifie le libell� (le titre) d'une t�che
     *
     * @param string $label
     * @return Task $this pour permettre le chainage des appels de m�thodes
     */
    public function setLabel($label)
    {
        $this->label=$label;
        return $this;
    }

    /**
     * Retourne le path du fichier qui contient la sortie g�n�r�e lors de la
     * derni�re ex�cution de la t�che ou null si aucun nom de fichier de sortie
     * n'a �t� d�finit.
     *
     * @return path|null
     */
    public function getOutput()
    {
        return $this->output;
    }
    
    /**
     * Modifie le path du fichier qui contient la sortie g�n�r�e par la t�che
     *
     * @param path $output
     * 
     * @return Task $this pour permettre le chainage des appels de m�thodes
     */
    public function setOutput($output)
    {
        $this->output=$output;
        return $this;
    }
    
    /**
     * Convertit un nom (jour, lundi, mars) ou une abr�viation (j., lun,
     * mar) utilis�e dans la date de programmation d'une t�che et retourne
     * le num�ro interne correspondant.
     * 
     * Si l'argument pass� est d�j� sous la forme d'un num�ro, retourne ce
     * num�ro.
     * 
     * 
     * @param mixed $value un entier ou une chaine � convertir.
     * @param string $what le type d'abr�viation recherch�. Doit �tre une des
     * valeurs suivantes : 'units' (unit�s de temps telles que jours, mois...),
     * 'minutes', 'hours', 'mday' (noms de jours) ou 'mon' (noms de mois).
     * 
     * @return int
     * 
     * @throws Exception si l'abr�viation utilis�e n'est pas reconnue.
     */
    private function convertAbbreviation($value, $what='units')
    {
        static $convert=array
        (
            'units'=>array
            (
                's'=>'seconds', 'sec'=>'seconds', 'second'=>'seconds', 'seconde'=>'seconds',
                'mn'=>'minutes', 'min'=>'minutes', 'minute'=>'minutes',
                'h'=>'hours', 'hour'=>'hours', 'heure'=>'hours',
                'd'=>'mday', 'j'=>'mday', 'day'=>'mday', 'jour'=>'mday',
                'mon'=>'mon', 'month'=>'mon', 'monthe'=>'mon', 'moi'=>'mon', // comme le s est enlev� mois->moi
            ),
            'seconds'=>array(),
            'minutes'=>array(),
            'hours'=>array(),
            'mday'=>array   // jours = num�ro wday retourn� par getdate + 1000
            (
                'dimanche'=>1000, 'lundi'=>1001, 'mardi'=>1002, 'mercredi'=>1003, 'jeudi'=>1004, 'vendredi'=>1005, 'samedi'=>1006,
                'dim'=>1000, 'lun'=>1001, 'mar'=>1002, 'mer'=>1003, 'jeu'=>1004, 'ven'=>1005, 'sam'=>1006,
                'sunday'=>1000, 'monday'=>1001, 'tuesday'=>1002, 'wednesday'=>1003, 'thursday'=>1004, 'friday'=>1005, 'saturday'=>1006,
                'sun'=>1000, 'mon'=>1001, 'tue'=>1002, 'wed'=>1003, 'thu'=>1004, 'fri'=>1005, 'sat'=>1006,
            ),
            'mon'=>array
            (
                'janvier'=>1, 'f�vrier'=>2, 'fevrier'=>2, 'mars'=>3, 'avril'=>4, 'mai'=>5, 'juin'=>6,
                'juillet'=>7, 'ao�t'=>8, 'aout'=>8, 'septembre'=>9, 'octobre'=>10, 'novembre'=>11, 'd�cembre'=>12, 'decembre'=>12,
        
                'january'=>1, 'february'=>2, 'march'=>3, 'april'=>4, 'may'=>5, 'june'=>6,
                'july'=>7, 'august'=>8, 'september'=>9, 'october'=>10, 'november'=>11, 'december'=>12,
        
                'jan'=>1, 'f�v'=>2, 'fev'=>2, 'feb'=>2, 'mar'=>3, 'avr'=>4, 'apr'=>4, 'mai'=>5, 'may'=>5,
                'juil'=>7, 'jul'=>7, 'aug'=>7, 'sep'=>9, 'oct'=>10, 'nov'=>11, 'd�c'=>12, 'dec'=>12,
            )
        );
    
        // Si la valeur est un nombre, on retourne ce nombre
        if (is_int($value) or ctype_digit($value)) return (int) $value; 
    
        // Fait la conversion
        if (!isset($convert[$what]))
            throw new Exception(__FUNCTION__ . ', argument incorrect : ' . $what);
            
        $value=rtrim(strtolower($value),'.');
        if ($value !='s') $value=rtrim($value, 's');
        
        if (!isset($convert[$what][$value]))
            switch($what)
            {
                case 'units':   throw new Exception($value . ' n\'est pas une unit� de temps valide');
                case 'seconds': throw new Exception($value . ' ne correspond pas � des seconds');
                case 'minutes': throw new Exception($value . ' ne correspond pas � des minutes');
                case 'hours':   throw new Exception($value . ' ne correspond pas � des heures');
                case 'mday':    throw new Exception($value . ' n\'est pas un nom de jour valide');
                case 'mon':     throw new Exception($value . ' n\'est pas un nom de mois valide');
                default:        throw new Exception($value . ' n\'est pas une unit� ' . $what . ' valide');
            } 
        return $convert[$what][$value];     
    }

    /**
     * Calcule la date et l'heure de prochaine ex�cution d'une t�che.
     * 
     * Par d�faut, la fonction retourne la date de prochaine ex�cution de la 
     * t�che (ie la prochaine date/heure qui soit sup�rieure � l'heure actuelle)
     * mais vous pouvez passer un timestamp en param�tre pour obtenir la 
     * date/heure qui suit imm�diatement ce timestamp.
     * 
     * Cela permet par exemple de calculer des dates dans le pass� ou bien
     * les dates suivantes d'ex�cution.
     * 
     * La fonction fait de son mieux pour conserver la date et l'heure 
     * d'ex�cution initialement pr�vues pour la t�che (par exemple si une t�che 
     * a �t� initialement programm�e � 12h 53min 25sec et qu'elle se r�p�te 
     * toutes les heures, les minutes et les secondes seront � chaque fois 
     * conserv�es).
     * 
     * Cependant ce n'est pas toujours possible : si une t�che est programm�e 
     * le 31 janvier avec l'option "r�p�ter tous les mois", la fonction ne pourra
     * pas retourner "31 f�vrier" et ajustera la date en cons�quence (2 ou 3
     * mars selon que l'ann�e est bissextile ou non).
     *
     * @param timestamp $now
     * @return null|timestamp
     */
    public function computeNext($now=null)
    {
        // R�cup�re l'heure actuelle
        if (is_null($now)) $now=time();

        $next=$this->getNext();
        $time=$this->getTime();
        $last=$this->getLast();
        
        if (! is_null($next) && ($next>=$now)) return $next;
        

        // Si la t�che n'est pas planifi�e, prochaine ex�cution=jamais
        if ($time===null) return null;
        
        // Si la t�che a d�j� �t� ex�cut�e et qu'elle n'est pas r�p�titive, prochaine ex�cution=jamais
        if (!is_null($last) && is_null($this->repeat)) return null;
            
        // Si la t�che est planifi�e "d�s que possible" et n'a pas encore �t� ex�cut�e, prochaine ex�cution=d�s que possible
        if ($time===0 && is_null($last)) return 0;
        
        // Si la t�che est planifi�e pour plus tard, prochaine ex�cution=date indiqu�e
        if ($time > $now) return $time;
        
        // Si la t�che �tait planifi�e mais n'a pas �t� ex�cut�e � l'heure souhait�e, prochaine ex�cution=heure pr�vue (+erreur t�che d�pass�e)
        if ($time<=$now && is_null($last)) return $time; // $now;
        
        // La t�che n'est pas r�p�titive, prochaine ex�cution : jamais
        if (is_null($this->repeat)) return null;
        
        // On a un repeat qu'il faut analyser pour d�terminer la prochaine date
        
        // Pour chaque unit� valide, $minmax donne le minimum et le maximum autoris�s
        static $minmax=array
        (
            'seconds'=>array(0,59), 'minutes'=>array(0,59), 'hours'=>array(0,23),
            'mday'=>array(1,31), 'mon'=>array(1,12), 'wday'=>array(1000,1006)
        );
    
        // Dur�e en secondes de chacune des p�riodes autoris�es
        static $duration=array
        (
            'seconds'=>1, 'minutes'=>60, 'hours'=>3600, 'mday'=>86400,
        );

        // Analyse repeat pour extraire le nombre, l'unit� et le filtre 
        $nb=$unit=$sep=$filterString=null;
        sscanf($this->repeat, '%d%[^/,]%[/,]%s', $nb, $unit, $sep, $filterString);
        if ($nb<=0)
            throw new Exception('nombre d\'unit�s invalide : ' . $this->repeat);
        
        // Convertit l'unit� indiqu�e en unit� php telle que retourn�e par getdate()
        $unit=self::convertAbbreviation(trim($unit), 'units');
        
        // Heure de d�but des calculs : l'heure d'ex�cution pr�vue (si <> asap), heure de cr�ation sinon
        $next=$time; // et si 0 ?
        if ($next===0) $next=$this->getCreation();
        
        // Essaie de d�terminer l'heure de prochaine ex�cution (d�but + n fois la p�riode indiqu�e)
        if ($unit!='mon')// non utilisable pour les mois car ils ont des dur�es variables
            $next+= ($nb * $duration[$unit]) * (floor(($now-$next)/($nb * $duration[$unit]))+1);

        // Incr�mente avec la p�riode demand�e juqu'� ce qu'on trouve une date dans le futur
        $t=getdate($next);
        $k=0;
        while ($next<=$now)
        {
            $t[$unit]+=$nb;
            $next=mktime($t['hours'],$t['minutes'],$t['seconds'],$t['mon'],$t['mday'],$t['year']);
            $k++;
            if ($k > 100) die('INFINITE LOOP here');
        } 
                
        // Si on n'a aucun filtre, termin�
        if (is_null($filterString))
            return $next;
        
        // Si on a un filtre, cr�e un tableau contenant toutes les valeurs autoris�es
        $filter=array();
        $min=$max=null;
        foreach (explode(',', $filterString) as $range)
        {
            sscanf($range, '%[^-]-%[^-]', $min, $max);
                
            // Convertit min si ce n'est pas un entier
            $tag=$unit;
            $min=self::convertAbbreviation($min,$unit);
            if ($min>=1000) $tag='wday'; // nom de jour
            if ($min<$minmax[$tag][0] or $min>$minmax[$tag][1]) 
                throw new Exception('Filtre invalide, '.$min.' n\'est pas une valeur de type '.$tag.' correcte');                
    
            if (is_null($max)) 
                $max=$min;
            else
            {
                // Convertit max si ce n'est pas un entier
                $max=self::convertAbbreviation($max,$unit);
                if ($max>1000 && $tag!='wday')
                    throw new Exception('Intervalle invalide : '.$max.' n\'est pas du m�me type que l\'�l�ment de d�but de p�riode');
                if ($max<$minmax[$tag][0] or $max>$minmax[$tag][1]) 
                    throw new Exception('Filtre invalide, '.$max.' n\'est pas une valeur de type '.$tag.' correcte');                
            }                
    
            // G�n�re toutes les valeurs entre $min et $max
            $k=0;
            for ($i=$min;;)
            {
                $filter[$i]=true;
                ++$i;
                if ($i>$max) break;
                if ($i>$minmax[$tag][1]) $i=$minmax[$tag][0];
                if(++$k>60) 
                {
                    echo 'intervalle de ',$min, ' � ', $max, ', tag=', $tag, ', min=', $minmax[$tag][0], ', max=', $minmax[$tag][1], '<br />';
                    throw new Exception('Filtre invalide, v�rifiez que l\'unit� correspond au filtre'); 
                }
            }
        }
//        echo "Filtre des valeurs autoris�es : ", var_export($filter, true), "\n";
        
        // Regarde si le filtre accepte la date obtenue, sinon incr�emente la date de nb unit�s et recommence
        for(;;)
        {
            // Teste si la date en cours passe le filtre
            $t=getdate($next);
            switch($unit)
            {
                case 'seconds':
                case 'minutes':
                case 'hours':
                case 'mon':
                    if (isset($filter[$t[$unit]])) return $next;
                    break;
                case 'mday':
                    if (isset($filter[$t[$unit]]) or isset($filter[$t['wday']+1000])) return $next;
                    break;
            }
    
            // Passe � la date suivante et recommence 
            $t[$unit]+=$nb;
            $next=mktime($t['hours'],$t['minutes'],$t['seconds'],$t['mon'],$t['mday'],$t['year']);
        }
        
        // Stocke et retourne le r�sultat
        return $next;
    }
    
    
    /**
     * Convertit un timestamp en chaine utilisable pour le tri xapian
     * (en attendant qu'on cr�e des cl�s de tri correct pour les entiers)
     *
     * Attention : fonction temporaire, ne pas utiliser.
     * 
     * @param null|0|timestamp $timestamp
     * @return string
     */
    public static function timestampToString($timestamp)
    {
        if (is_null($timestamp)) return null;
        //if (! is_int($timestamp)) return gettype($timestamp). ' not int'; // erreur
        if ($timestamp===0) return '0';
        return strftime('%Y%m%d%H%M%S',$timestamp);
    }
    
    /**
     * Convertit une chaine en timestamp
     *
     * Attention : fonction temporaire, ne pas utiliser.
     * 
     * @param string $string
     * @return timestamp
     */
    public static function stringToTimestamp($string)
    {
        if (is_null($string)) return null;
        if ($string==='0') return 0;
        if (strlen($string)!==14 || !ctype_digit($string)) return null; // erreur
        // AAAAMMJJHHMMSS
        // 01234567890123
        return mktime
        (
            (int)substr($string,  8, 2), // heures
            (int)substr($string, 10, 2), // minutes
            (int)substr($string, 12, 2), // secondes
            (int)substr($string,  4, 2), // mois
            (int)substr($string,  6, 2), // jour
            (int)substr($string,  0, 4)  // ann�e
        );
    }
}
//
//for($i=1; $i<10000; $i++)
//{
//    $j=strtr($i/3.14,',','.');
//    //$x=base64_encode($j);
//    $x=convert_uuencode($j);
//    //$y=(double)base64_decode($x);
//    $y=(double)convert_uudecode($x);
//    
//    $k=(int)round($y*3.14);
//    echo 'i=', $i, ', j=', $j, ', x=', $x, ', y=', $y, ', k=', $k, ($i===$k ? '. ok.' : '. ERREUR.'), '<br />';
//}
//die();
/*
echo '<pre>';
function GenerationCle($Texte,$CleDEncryptage)
  {
  $CleDEncryptage = md5($CleDEncryptage);
  $Compteur=0;
  $VariableTemp = "";
  for ($Ctr=0;$Ctr<strlen($Texte);$Ctr++)
    {
    if ($Compteur==strlen($CleDEncryptage))
      $Compteur=0;
    $VariableTemp.= substr($Texte,$Ctr,1) ^ substr($CleDEncryptage,$Compteur,1);
    $Compteur++;
    }
  return $VariableTemp;
  }

function Crypte($Texte,$Cle)
  {
  srand((double)microtime()*1000000);
  $CleDEncryptage = md5(rand(0,32000) );
  $Compteur=0;
  $VariableTemp = "";
  for ($Ctr=0;$Ctr<strlen($Texte);$Ctr++)
    {
    if ($Compteur==strlen($CleDEncryptage))
      $Compteur=0;
    $VariableTemp.= substr($CleDEncryptage,$Compteur,1).(substr($Texte,$Ctr,1) ^ substr($CleDEncryptage,$Compteur,1) );
    $Compteur++;
    }
  return base64_encode(GenerationCle($VariableTemp,$Cle) );
  }

function Decrypte($Texte,$Cle)
  {
  $Texte = GenerationCle(base64_decode($Texte),$Cle);
  $VariableTemp = "";
  for ($Ctr=0;$Ctr<strlen($Texte);$Ctr++)
    {
    $md5 = substr($Texte,$Ctr,1);
    $Ctr++;
    $VariableTemp.= (substr($Texte,$Ctr,1) ^ $md5);
    }
  return $VariableTemp;
  }
//Exemple de l'appel aux fonctions Crypte et Decrypte :

$Cle = "MotDePasseSuperSecret";
$MonTexte = 20;
$TexteCrypte = Crypte($MonTexte,$Cle);
$TexteClair = Decrypte($TexteCrypte,$Cle);
echo "Texte original : $MonTexte <Br>";
echo "Texte crypt� : $TexteCrypte <Br>";
echo "Texte d�crypt� : $TexteClair <Br>";
die();
*/
?>