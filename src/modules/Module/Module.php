<?php
/**
 * @package     fab
 * @subpackage  module
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Module.php 1227 2010-11-30 19:07:22Z daniel.menard.bdsp $
 */


/**
 * Gestionnaire de modules et classe anc�tre pour tous les modules de fab.
 *
 * @package     fab
 * @subpackage  module
 */
abstract class Module
{
    public $path;
    public $module;
    public $action;
    public $method;
    public $searchPath=array();
    public $config=null;

    /**
     * La requ�te en cours
     *
     * @var Request
     */
    public $request=null;


    /**
     * La r�ponse g�n�r�e par l'action ex�cut�e.
     *
     * @var Response
     */
    protected $response = null;


    /**
     * Permet � un module de s'initialiser une fois que sa configuration
     * a �t� charg�e.
     *
     * La m�thode <code>initialize()</code> est destin�e � �tre surcharg�e par
     * les modules descendants.
     *
     * Lorsqu'un module est cr��, son constructeur <code>__construct()</code>
     * est appell�. Le module peut alors faire certaines initialisations, mais,
     * � ce stade, la configuration du module n'a pas encore �t� charg�e.
     *
     * La m�thode <code>initialize()</code> permet de rem�dier � ce probl�me :
     * elle est appell�e par la m�thode {@link loadModule()} une fois que la
     * configuration du module a �t� charg�e.
     *
     * Remarque :
     * Si un module surcharge cette m�thode, il doit appeller la m�thode anc�tre
     * de son parent : <code>parent::initialize()</code>.
     *
     */
    public function initialize()
    {

    }

    /**
     * Cr�e une instance du module dont le nom est pass� en param�tre.
     *
     * La fonction se charge de charger le code source du module, de cr�er un
     * nouvel objet et de charger sa configuration
     *
     * @param string $module le nom du module � instancier
     * @return Module une instance du module
     *
     * @throws ModuleNotFoundException si le module n'existe pas
     * @throws ModuleException si le module n'est pas valide (par exemple
     * pseudo module sans cl� 'module=' dans la config).
     */
    public static function loadModule($module, $fab=false)
    {
        // Recherche le r�pertoire contenant le module demand�
        if ($fab)
            $moduleDirectory=Utils::searchFileNoCase
            (
                $module,
                Runtime::$fabRoot.'modules' // r�pertoire "/modules" du framework
            );
        else
            $moduleDirectory=Utils::searchFileNoCase
            (
                $module,
                Runtime::$root.'modules',   // r�pertoire "/modules" de l'application
                Runtime::$fabRoot.'modules' // r�pertoire "/modules" du framework
            );

        // G�n�re une exception si on ne le trouve pas
        if (false === $moduleDirectory)
        {
            throw new ModuleNotFoundException($module);
        }

        // Le nom du r�pertoire nous donne le nom exact du module
        $h=basename($moduleDirectory);
//        if ($h!==$module)
//            echo 'Casse diff�rente sur le nom du module. Demand�=',$module, ', r�el=', $h, '<br />';
        $module=$h;

        $moduleDirectory .= DIRECTORY_SEPARATOR;

        // V�rifie que le module est activ�
        // if ($config['disabled'])
        //     throw new Exception('Le module '.$module.' est d�sactiv�');

        $singleton=false;
        // Si le module a un fichier php, c'est un vrai module, on le charge
        if (file_exists($path=$moduleDirectory.$module.'.php'))
        {
            // Cr�e une nouvelle instance du module
            $interfaces=class_implements($module);
            if (isset($interfaces['Singleton']) && call_user_func(array($module, 'hasInstance')))
            {
                $object= call_user_func(array($module, 'getInstance'));
                $singleton=true;
            }
            else
            {
                $object=new $module();


                // V�rifie que c'est bien une classe d�scendant de 'Module'
                if (! $object instanceof Module)
                    throw new ModuleException("Le module '$module' est invalide : il n'h�rite pas de la classe anc�tre 'Module' de fab");

                $object->searchPath=array
                (
                    Runtime::$fabRoot.'core'.DIRECTORY_SEPARATOR.'template'.DIRECTORY_SEPARATOR, // fixme: on ne devrait pas fixer le searchpath ici
                    Runtime::$root
                );

                $transformer=array($object, 'compileConfiguration');

                // Cr�e la liste des classes dont h�rite le module
                $ancestors=array();
                $class=new ReflectionClass($module);
                while ($class !== false)
                {
                    array_unshift($ancestors, $class);
                    $class=$class->getParentClass();
                }

                // Configuration le module
                $config=array();
                foreach($ancestors as $class)
                {
                    // Fusionne la config de l'anc�tre avec la config actuelle
                    Config::mergeConfig($config, self::getConfig($class->getName(), $transformer));

                    // Ajoute le r�pertoire de l'an�tre dans le searchPath du module
                    $dir=dirname($class->getFileName());
                    array_unshift($object->searchPath, $dir.DIRECTORY_SEPARATOR);

                    // Si l'application � un r�pertoire portant le m�me nom, on l'ajoute aussi dans le searchPath
                    // Pour surcharger des templates, etc.
                    /*
                        en fait n'a pas de sens : si on cr�e un r�pertoire ayant le
                        m�me nom, il sera consid�r� comme un pseudo module
                    */
                    if (strncmp($dir, Runtime::$fabRoot, strlen(Runtime::$fabRoot))===0)
                    {
                        $appdir=Runtime::$root.substr($dir, strlen(Runtime::$fabRoot));
                        if (file_exists($appdir))
                            array_unshift($object->searchPath, $appdir.DIRECTORY_SEPARATOR);
                    }

                }

                // Stocke la config du module
                $object->config=$config;
            }
        }

        // Sinon, il s'agit d'un pseudo-module : on doit avoir un fichier de config avec une cl� 'module'
        else
        {
            $config=self::getConfig($module);
            // pb: � ce stade on ne sait pas quel transformer utiliser.
            // du coup la config est charg�e (et donc stock�e en cache) sans
            // aucune transformation. Cons�quence : si on cr�e un pseudo module
            // qui h�rite d'un module utilisant un transformer sp�cifique
            // (exemple routing), on va merger une config compil�e (celle du
            // vrai module) avec une config non compil�e (celle du pseudo module)
            // ce qui fera n'importe quoi.
            // Donc : un pseudo module ne peut pas h�riter d'un module ayant un
            // transformer sp�cifique (il faut faire un vrai module dans ce cas).
            // Question : comment v�rifier �a ?


            debug && Debug::log('Chargement du pseudo-module %s', $module);

            if (isset($config['module']))
            {
                // Charge le module correspondant
                $parent=self::loadModule($config['module']);

                // Applique la config du pseudo-module � la config du module
                Config::mergeConfig($parent->config, $config);

                if (! class_exists($module, false))
                {
                    eval
                    (
                        sprintf
                        (
                            '
                                /**
                                  * %1$s est un pseudo-module qui h�rite de {@link %2$s}.
                                  */
                                class %1$s extends %2$s
                                {
                                }
                            ',
                            $module,
                            $config['module']
                        )
                    );
                }

                $object=new $module();
                $object->config=$parent->config;
                $object->searchPath=$parent->searchPath;
            }
            else
            {
                // pseudo module implicite : on a juste un r�pertoire toto dans
                // le r�pertoire modules de l'application, mais on n'a ni fichier
                // toto.php ni config sp�cifique indiquant de quel module doit
                // h�riter toto. Dans ce cas, consid�re qu'on veut cr�er un
                // nouveau module qui h�rite du module de m�me nom existant dans fab.
                $object=self::loadModule($module, true);

                // dans ce cas pr�cis, pas de merge de la config car elle est
                // d�j� charg�e par l'appel au loadModule ci-dessus puisque c'est
                // le m�me nom

            }



            // Met � jour le searchPath du module
            array_unshift($object->searchPath, $moduleDirectory);
        }

        // Stocke le path du module et son nom exact
        $object->path=$moduleDirectory;
        $object->module=$module;
        debug && Debug::log($module . ' : %o', $object);

        if (!$singleton) $object->initialize();
        return $object;
    }

    // retourne la config du module indiqu� (fab + app) et (normale + env)
    // ne remonte pas les ascendants
    private static function getConfig($module, array $transformer=null)
    {
        $config=array();
        $dir='config'.DIRECTORY_SEPARATOR.$module.'.';
        foreach(array(Runtime::$fabRoot, Runtime::$root) as $root)
        {
            // Charge la configuration normale
            if (file_exists($path = $path=$root.$dir.'config'))
            {
                Config::mergeConfig($config, Config::loadFile($path, $transformer));
            }

            // Charge la configuration sp�cifique � l'environnement en cours
            if (! empty(Runtime::$env))
            {
                if (file_exists($path=$root.$dir.Runtime::$env.'.config'))
                {
                    Config::mergeConfig($config, Config::loadFile($path, $transformer));
                }
            }
        }
        return $config;
    }

    /**
     * Compile la configuration du module avant que celle-ci ne soit mise en
     * cache par le framework.
     *
     * La m�thode par d�faut ne fait rien : elle retourne inchang�e la
     * configuration qu'on lui passe en param�tre mais un module peut surcharger
     * cette m�thode s'il a une configuration sp�cifique qui doit �tre compil�e
     * (exemple : Routing).
     *
     * @param array $config
     * @return array
     */
    public function compileConfiguration(array $config)
    {
        return $config;
    }

    private function configureAction($action)
    {
        $trace=false;

        $this->action=$action;

        // Utilise la r�flexion pour tester si l'action est une m�thode du module
        $class=new ReflectionObject($this);
        if($trace)echo 'configureAction(', $action, ')<br />';

        $action='action'.$action;

        // Le tableau $configs contiendra toutes les configs qu'on rencontre (pseudo action 1 -> psued action 2 -> vrai action)
        $configs=array();

        // Si l'action n'est pas une m�thode de la classe, c'est une pseudo action : on suit la chaine
        $isMethod=true;
        while (! $class->hasMethod($action))
        {
            if($trace)echo $action, " n'est pas une m�thode de l'objet ", $class->getName(), '<br />';

            // Teste si la config contient une section ayant le nom exact de l'action
            $config=null;
            if (isset($this->config[$action]))
            {
                if($trace)echo "La cl� $action est d�finie dans la config<br/>";
                $config=$this->config[$action];
            }

            // Sinon, r�-essaie en ignorant la casse de l'action
            else
            {
                if($trace)echo "La cl� $action n'est pas d�finie dans la config<br/>Soit la casse n'est pas bonne, soit ce n'est pas une pseudo action<br />";
                if($trace) echo "Lancement d'une recherche insensible � la casse de la cl� $action<br />";

                if (Config::get('urlignorecase'))
                {
                    foreach($this->config as $key=>$value)
                    {
                        if(strcasecmp($key, $action)===0)
                        {
                            $action=$key;
                            $config=$value;
                            if($trace)echo 'Cl� trouv�e. Nom indiqu� dans la config : ', $action, '<br />';
                            break; // sort du for
                        }
                    }
                }
                if (is_null($config))
                {
                    // Il n'y a rien dans la config pour cette action
                    // C'est soit une erreur (bad action) soit un truc que le module g�rera lui m�me (exemple : fabweb)
                    // On ne peut pas remonter plus loin, exit while
                    if($trace)echo 'La config ne contient aucune section ', $action, '<br />';
                    if($trace)echo "Soit c'est une erreur (bad action), soit un truc sp�cifique au module (exemple fabweb)<br />";
                    $isMethod=false;
                    break;
                }
            }

            // Stocke la config obtenue (on la chargera plus tard)
            $configs=array($action=>$config) + $configs;

            // Si la cl� action n'est pas d�finie, on ne peut pas remonter plus, exit while
            if (!isset($config['action']))
            {
                if($trace)echo "La config de l'action $action n'a pas de cl� 'action', on ne peut pas remonter plus<br/>";
//                pre($config);
                $isMethod=false;
                break;
            }

            // On a une nouvelle action, on continue � remonter la chaine
//            array_unshift($configs, $config);
            $action=$config['action'];
        }

        if ($isMethod)
        {
            if ($class->getMethod($action)->getName() !== $action)
            {
                echo "Casse diff�rente dans le nom de l'action indiqu�=", $action, ', r�el=',$class->getMethod($action)->getName(), '<br />';
            }

            // M�morise le nom de la m�thode qui devra �tre appell�e
            $action=$this->method=$class->getMethod($action)->getName(); // On n'utilise pas directement '$action' mais la reflection pour avoir le nom exact de l'action, avec la bonne casse. Cela permet aux fonctions qui testent le nom de l'action (exemple : homepage) d'avoir le bon nom

            if (isset($this->config[$action]))
            {
                if($trace)echo "La vrai action $action a une config<br/>";
                $configs=array($action=>$this->config[$action]) + $configs;
            }
            else
            {
                if($trace)echo "La vrai action $action n'a pas de config<br/>";
            }
        }
        else
        {
            if($trace) echo "L'action $action n'aboutit pas � une m�thode. Le module devra g�rer lui m�me <br />";
        }

        // Cr�e la configuration finale de l'action en fusionnant toutes les config otenues
//        debug && pre('Liste des configs � charger : ', $configs);
        foreach($configs as $config)
        {
            Config::mergeConfig($this->config, $config);
        }

//        // Enl�ve de la config toutes les cl�s 'actionXXX'
//        foreach($this->config as $key=>$value)
//        {
//            if (strncmp($key, 'action', 6)===0)
//                unset($this->config[$key]);
//        }
//echo 'Searchpath du module : ', var_export($this->searchPath,true), '<br />';
Utils::$searchPath=$this->searchPath; // fixme: hack pour que le code qui utilise Utils::searchPath fonctionne
//echo 'Config pour l\'action : <pre>';
//print_r($this->config);
//echo '</pre>';

//Config::clear();
//echo 'Config g�n�rale : <pre>';
Config::addArray($this->config);    // fixme: objectif : uniquement $this->config mais pb pour la config transversale (autoincludes...) en attendant : on recopie dans config g�n�rale
//print_r(Config::getAll());
//echo '</pre>';
//die();
    }

    /**
     * Lance l'ex�cution d'un module
     */
    public static function run(Request $request)
    {
        self::runAs($request, $request->getModule(), $request->getAction());
    }

    public static function getModuleFor(Request $request)
    {
        Utils::clearSearchPath();
        $module=self::loadModule($request->getModule());
        $module->configureAction($request->getAction());
        $module->request=$request;
        return $module;
    }

    public static function runAs(Request $request, $module, $action, $asSlot=false)
    {
        Utils::clearSearchPath();
        $module=self::loadModule($module);
        $module->request=$request;
        $module->configureAction($action);
        $module->execute($asSlot);

        // Exp�rimental : enregistre les donn�es du formulaire si un param�tre "_autosave" a �t� transmis
        if ($request->has('_autosave'))
        {
            Runtime::startSession();
            $_SESSION['autosave'][$request->get('_autosave')]=$request->clear('_autosave')->getParameters();
        }
    }

    // Experimental : do not use, r�cup�ration des donn�es enregistr�es pour un formulaire
    public static function formData($name)
    {
        Runtime::startSession();

        if (!isset($_SESSION['autosave'][$name])) return array();
        return $_SESSION['autosave'][$name];
    }


    /**
     * Fonction appel�e avant l'ex�cution de l'action demand�e.
     *
     * Par d�faut, preExecute v�rifie que l'utilisateur dispose des droits
     * requis pour ex�cuter l'action demand�e (cl� 'access' du fichier de
     * configuration).
     *
     * Les modules d�riv�s peuvent utiliser cette fonction pour
     * r�aliser des pr�- initialisations ou g�rer des pseudo- actions. Si vous
     * surchargez cette m�thode, pensez � appeler parent::preExecute().
     *
     * Une autre utilisation de cette fonction est d'interrompre le traitement en
     * retournant 'true'.
     *
     * @return bool true si l'ex�cution de l'action doit �tre interrompue, false
     * pour continuer normalement.
     */
    public function preExecute()
    {
    }

    /**
     * Ex�cute l'action demand�e
     *
     *    Cas de figure :
     *    1. Action fonctionnant selon le nouveau mode : l'action ne fait aucun echo, elle
     *       se contente de retourner un objet Response.
     *       - on se contente d'appeller Response::output()
     *       - le buffering mis en place ne sert � rien dans ce cas (mais il n'a rien "consomm�"
     *         non plus comme on n'a fait aucun echo).
     *    2. Action fonctionnant selon le nouveau mode, retourne un objet Response mais quelques
     *       echos ont �t� faits au pr�alable.
     *       - le buffering mis en place a collect� les echos
     *       - on fait Response::prependContent() avec ces echos
     *       - quand on ex�cute la r�ponse, cela g�n�re : le layout, les echos, le template.
     *    3. Action ancien mode : fait des echo ou des appels � template::Run. Ne retourne rien
     *       ou retourne autre chose qu'un objet Response.
     *       - le buffering mis en place a collect� les echos
     *       - on cr�e une HtmlResponse vide
     *       - on fait Response::prependContent() avec ces echos
     *       - quand on ex�cute la r�ponse, cela g�n�re : le layout, les echos.
     *    4. Ex�cution d'une t�che
     *       - dans ce cas, pas de buffering (sapi == cli)
     *       - l'action fait ses echos, ils sont envoy�s directement
     *       - pb : le layout n'a pas �t� envoy�. en fait, ce n'est pas un probl�me : une t�che n'a
     *         pas de layout.
     *    5. Une action veut absolument envoyer elle-m�me son contenu
     *       - pas de buffering (config : <output-buffering>false</output-buffering>)
     *       - ok si pas de layout
     *       - pb si layout : le r�sultat de l'action est envoy� avant le layout. Non g�r� pour
     *         le moment, volontairement (ci-dessous : output buffering)
     *
     */
    public final function execute($asSlot=false)
    {
        // Propose au module de g�rer lui-m�me l'ex�cution compl�te de l'action
        if (true === $this->preExecute()) return;

        // V�rifie que l'utilisateur en cours a le droit d'ex�cuter l'action demand�e
        $access=Config::get('access');
        if (! empty($access)) User::checkAccess($access);

        // V�rifie que l'action demand�e existe
        if ( is_null($this->method) || ! method_exists($this, $this->method))
            throw new ModuleActionNotFoundException($this->module, $this->action);

        // Cas particulier : l'action veut absolument fonctionner "comme avant"
//        if (false === Config::get('output-buffering', true))
//        {
//            if (strcasecmp(Config::get('layout'),'none')==0)
//                $this->callActionMethod();
//            else
//            {
//                $this->response = new LayoutResponse(); // TODO : ne pas g�n�rer de statut et d'ent�tes dans ce cas.
//                $ret=$this->response->outputLayout($this);
//            }
//        }
//        else
//        {
            // D�termine s'il faut bufferiser ou non la sortie g�n�r�e par l'action
            $buffering = php_sapi_name() !== 'cli';

            // Ex�cute l'action demand�e
            if ($buffering) ob_start();

            $this->response = $this->callActionMethod();

            if (! $this->response instanceof Response)
                $this->response = new LayoutResponse();

            if ($buffering)
                if ($output = ob_get_clean())
                    $this->response->prependContent($output);

            // G�n�re la r�ponse
            if ($asSlot)
                $this->response->outputContent();
            elseif ($this->response->hasLayout())
                $this->response->outputLayout($this);
            else
                $this->response->output();
//        }

        // Post-ex�cution
        $this->postExecute();
    }

    /**
     * M�thode appell�e par les layouts pour afficher le contenu utile de la r�ponse.
     */
    public function runAction()
    {
//        if (false === Config::get('output-buffering', true))
//            $this->callActionMethod();
//        else
            $this->response->outputContent();
    }


    /**
     * Appelle la m�thode correspondant � l'action demand�e en lui passant en param�tre
     * les arguments de la requ�te.
     *
     * @return mixed Retourne ce qu'a retourn� la m�thode (normallement, un objet Response).
     */
    protected function callActionMethod()
    {
        // Utilise la reflexion pour examiner les param�tres de l'action
        $reflectionModule = new ReflectionObject($this);
        $reflectionMethod = $reflectionModule->getMethod($this->method);
        $params = $reflectionMethod->getParameters();

        // On va construire un tableau args contenant tous les param�tres
        $args=array();
        foreach($params as $i=>$param)
        {
            // R�cup�re le nom du param�tre
            $name=$param->getName();

            // La requ�te a une valeur non vide pour ce param�tre : on le v�rifie et on l'ajoute
            if ($this->request->has($name))
            {
                $value = $this->request->get($name);

                if ($value !== '' && !is_null($value))
                {
                    // Tableau attendu : caste la valeur en tableau
                    if ($param->isArray() && !is_array($value))
                    {
                        $args[$name] = array($value);
                        continue;
                    }

                    // Objet attendu, v�rifie le type
                    if ($class = $param->getClass())
                    {
                        $class = $class->getName();
                        if (! is_null($class) && !$value instanceof $class)
                        {
                            throw new InvalidArgumentException
                            (
                                sprintf
                                (
                                    '%s doit �tre un objet de type %s',
                                    $name,
                                    $class
                                )
                            );
                        }
                        $args[$name]=$value;
                        continue;
                    }

                    // tout est ok
                    $args[$name] = $value;
                    continue;
                }
            }

            // Sinon, on utilise la valeur par d�faut s'il y en a une
            if (!$param->isDefaultValueAvailable())
            {
                throw new InvalidArgumentException
                (
                    sprintf
                    (
                        '%s est obligatoire',
                        $name
                    )
                );
            }

            // ok
            $args[$name] = $param->getDefaultValue();
        }

        // Appelle la m�thode avec la liste d'arguments obtenus
        debug && Debug::log('Appel de la m�thode %s->%s()', get_class($this), $this->method);
        return $reflectionMethod->invokeArgs($this, $args);
    }


    /**
     * M�thode appel�e apr�s l'ex�cution de l'action demand�e.
     *
     * Pour l'action demand�e, cette m�thode g�n�re des logs.
     *
     * Les fichiers de log se trouvent dans le r�pertoire <code>/data/log</code>
     * de l'application.
     *
     * Le path du fichier de log, relatif au r�pertoire <code>/data/log</code> est
     * indiqu� dans la cl� <code>logfile</code> du fichier de configuration. Dans
     * le nom du fichier, il est possible d'utiliser tous les flags support�s par
     * la fonction {@link strftime()} de php.
     *
     * Le format de chaque ligne du fichier de log est indiqu� dans la cl�
     * <code>logformat</code> du fichier de configuration.
     */
    public function postExecute()
    {
        // R�cup�re le nom du fichier de log, exit si aucun
        if (is_null($logFile=Config::get('logfile'))) return;

        // R�cup�re le format du fichier de log, exit si aucun
        if (is_null($logFormat=Config::get('logformat'))) return;

        // D�termine le r�pertoire o� seront stock�s les log
        $dir=Utils::makePath(Runtime::$root, 'data', 'log', dirname($logFile));

        // V�rifie qu'il existe, le cr�e si besoin est
        if (!is_dir($dir))
            if (!Utils::makeDirectory($dir)) return;

        // D�termine le nom du fichier de log
        $file=strftime(basename($logFile));

        // Construit la ligne de log
        if ('' === $log=$this->getLogData($logFormat)) return;

        // Ecrit la ligne de log dans le fichier
        file_put_contents($dir . DIRECTORY_SEPARATOR . $file, $log, FILE_APPEND);
    }

    /**
     * Construit la ligne de log.
     *
     * @param string $format le format.
     * @return string la ligne de log.
     */
    private function getLogData($format)
    {
        $log='';
        $items=preg_split('~%([A-Za-z0-9_.]+)~', $format, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach($items as $index=>$item)
        {
            // Les items d'indice pair sont �crits tels quels
            if (0 === $index %2)
                $log .= $item;
            else
            {
                $data=$this->getLogItem($item);
                if (! $data)
                    $data='-';
                else
                    // Neutralise les retours chariots
                    $data=str_replace(array("\r\n", "\n", "\r"), array(' ', ' ', ' '), $data);

                $log .= $data;
            }
        }

        if ($log) $log.="\n";
        return $log;
    }

    /**
     * Retourne la valeur d'un �l�ment � �crire dans le fichier de log.
     *
     * M�thode destin�e � �tre surcharg�e par les modules descendants.
     *
     * Noms d'items reconnus par cette m�thode :
     * - tous les flags support�s par la fonction {@link strftime()} de php
     * - user.xxx : la propri�t� 'xxx' de l'utilisateur en cours
     * - browser.xxx : la propri�t� 'xxx' du browser en cours (cf get_browser())
     * - ip : l'adresse ip du client
     * - host : le nom d'h�te du serveur en cours
     * - user_agent : la chaine identifiant le navigateur de l'utilisateur
     * - referer : la page d'o� provient l'utilisateur
     * - uri : l'adresse compl�te (sans le host) de la requ�te en cours
     * - query_string : les param�tres de la requ�te
     *
     * @param string $name le nom de l'item.
     *
     * @return string la valeur � �crire dans le fichier de log pour cet item.
     */
    protected function getLogItem($name)
    {
        // Date, heure, etc.
        if (strlen($name)===1) return strftime('%'.$name);

        // Important : les items dont le nom ne fait que un caract�re sont
        // r�serv�s � strftime. Tous les autres items que l'on cr�e doivent
        // obligatoirement avoir un nom d'au moins deux lettres.

        // Items sur l'utilisateur en cours
        if (substr($name, 0, 5)==='user.') return User::get(substr($name, 5));

        // Items sur le browser en cours (cf get_browser())
        if (substr($name, 0, 8)==='browser.')
        {
            static $browser=null;

            if (is_null($browser))
                $browser= isset($_SERVER['HTTP_USER_AGENT']) ? get_browser() : false;

            if ($browser===false) return '';

            $prop=substr($name, 8);
            if (!isset($browser->$prop)) return '';
            return $browser->$prop;
        }

        // Autres items
        switch($name)
        {
            // Requ�te http
            case 'ip':              return $_SERVER['REMOTE_ADDR'];
            case 'host':            return $_SERVER['HTTP_HOST'];
            case 'user_agent':      return $_SERVER['HTTP_USER_AGENT'];
            case 'referer':         return $_SERVER['HTTP_REFERER'];
            case 'uri':             return $_SERVER['REQUEST_URI'];
            case 'query_string':    return $_SERVER['QUERY_STRING'];
        }

        // Item inconnu
        return '';
    }

    public static function forward($fabUrl)
    {
        Routing::dispatch($fabUrl);
        Runtime::shutdown();
    }

    /**
     * Convertit l'alias d'un script javascript ou d'une feuille de style
     *
     * La fonction utilise les alias d�finis dans la configuration
     * (cl�s jsalias et cssalias) et traduit les path qui figurent dans cette
     * liste.
     *
     * @param string|array $path le path � convertir ou un tableau de path �
     * convertir
     * @param string $alias la cl� d'alias � utiliser ('cssalias' ou 'jsalias')
     * @return array un tableau contenant le ou les path(s) converti(s)
     */
    private function CssOrJsPath($path, $aliasKey)
    {
        if (is_null($path)) return array();
        $alias=Config::get($aliasKey);

        $isArray=is_array($path);
        $path=(array)$path;

        foreach($path as & $item)
        {
            if (isset($alias[$item]))
                $item=$alias[$item];
        }
        return $path;
    }

    /**
     * Retourne le titre de la page.
     *
     * Typiquement, pour une page html, c'est le titre qui appara�tra dans la
     * balise title de la section <code>head</code>.
     *
     * Par d�faut, la m�thode retourne le contenu de la cl� <code><title></code>
     * indiqu�e dans la configuration, mais les modules descendants peuvent
     * surcharger cette m�thode si un autre comportement est souhait�.
     *
     * @return string le titre de la page.
     */
    public function getPageTitle()
    {
        return Config::get('title','Votre site web');
    }

    /**
     * Retourne le code html permettant d'inclure les feuilles de style CSS dont
     * la page a besoin.
     *
     * Typiquement, il s'agit du code html qui sera inclus dans la section
     * <code>head</code> de la page.
     *
     * Par d�faut, la m�thode construit une balise html <code>link</code> pour
     * chacune des feuilles de style css indiqu�e dans la cl� <code><css></code>
     * de la configuration.
     *
     * Les modules descendants peuvent surcharger cette m�thode s'ils souhaitent
     * un autre comportement (exemple d'utilisation : compresser toutes les
     * feuilles de style en une seule).
     *
     * @return string le code html � inclure.
     */
    public function GetCssLinks()
    {
        die("La m�thode getCssLinks() n'est plus support�e. A la place, utilisez &lt;css /&gt; dans vos templates.");
    }


    /**
     * Retourne un tableau d'�l�ments � inclure dans la page html g�n�r�e tels que les scripts
     * javascripts, les feuilles de styles css, les metas, les liens, etc.
     *
     * @param string $type le type d'asset � charger. Doit correspondre aux cl�s existantes dans
     * la config. Valeurs possibles pour le moment : 'js','css','metas' ou 'links'.
     *
     * @param string $placement filtre utilis� pour charger les assets. Seuls les items dont
     * l'attribut placement correspond � la valeur indiqu�e seront charg�s.
     * Valeurs possibles : 'all','top','bottom' ou une valeur personnalis�e propre � l'application.
     * Lorsque placement vaut 'all', tous les assers sont charg�s sans conditions.
     *
     * @param array $defaults les attributs � retourner et leur valeur par d�faut.
     *
     * @param string $defaultAttribute le nom de l'attribut par d�faut. Dans la configuration, si
     * un asset est d�clar� sous sa forme courte (exemple <item>jquery.js</item>) la valeur sera
     * stock� dans l'attribut indiqu� et les autres attributs seront initialis�s avec leur
     * valeur par d�faut.
     *
     * @return array un tableau d'assets. Chaque �lement est un tableau ayant exactement les cl�s
     * indiqu�es dans $defaults.
     */
    protected function getAssets($type, $placement='all', $defaults=array(), $defaultAttribute='src')
    {
        /*
         * On peut se retrouver dans la situation ou un m�me fichier js (ou css, etc.) est indiqu�
         * plusieurs fois dans la config. Par exemple, l'action Backup de AdminDatabases "sait"
         * qu'elle a besoin de jquery et elle l'indique dans la config, car jQuery n'est pas charg�e
         * par d�faut dans la config g�n�rale.
         *
         * Le probl�me c'est que si plus tard, l'application charge syst�matiquement jQuery, alors
         * celle-ci sera charg�e deux fois et si apr�s le premier chargement, des plugins ont �t�
         * install�s (par exemple datePicker), alors le deuxi�me chargement va tout r�initialiser.
         * Pour �viter cela, il faut qu'on d�doublonne les items. On se sert pour cela de l'attribut
         * indiqu� dans $defaultAttribute.
         *
         * On fait une premi�re passe en chargeant tous les items (quel que soit leur placement) et
         * en les d�doublonnant � la vol�e.
         *
         * On fait ensuite une seconde passe en ne gardant que ceux qui correspondent au placement
         * demand�. DM,30/11/10
         */

        // Charge les alias d�finis dans la config
        $alias=Config::get('alias');

        // Premi�re passe : d�doublonne tous les items sans tenir compte de leur placement
        $result = array();
        foreach ((array) Config::get($type) as $item)
        {
            // Cas particulier : l'item est une chaine (on n'a que l'attribut par d�faut)
            if (is_scalar($item))
                $item=array($defaultAttribute=>$item);

            // Ajoute les attributs par d�faut
            $item = array_merge($defaults, $item);

            // Convertit les alias utilis�s
            if (isset($alias[$item[$defaultAttribute]])) $item[$defaultAttribute] = $alias[$item[$defaultAttribute]];

            // D�doublonnage
            if ($item['placement'] === 'top' || !isset($result[$item[$defaultAttribute]]))
                $result[$item[$defaultAttribute]] = $item;
        }

        // Seconde passe : ne garde que les items correspondants au placement demand�
        if ($placement !== 'all')
        {
            foreach($result as $key=>$item)
            {
                if ($item['placement'] !== $placement)
                    unset($result[$key]);
            }
        }

        // Retourne le r�sultat
        return $result;
    }

    /**
     * Retourne la liste des scripts javascript � inclure dans la page.
     *
     * @param string $placement filtre de placement � utiliser. Seuls les items dont l'attribut
     * "placement" correspond � la valeur indiqu�e seront retourn�e. La valeur "all" charge tous les
     * items sans condition.
     *
     * @return array un tableau d�crivant les items � inclure. Chaque �l�ment est un tableau ayant
     * les cl�s suivantes :
     * - placement
     * - condition
     * - charset
     * - defer
     * - src
     * - type
     */
    public function getScripts($placement='all')
    {
        return $this->getAssets
        (
            'js',
            $placement,
            array
            (
                'placement' =>'bottom',
                'condition' =>'',
                'charset'   => '',
                'defer'     => '',
                'src'       => '',
                'type'      => 'text/javascript',
            ),
            'src'
        );
    }


    /**
     * Retourne la liste des feuilles de style CSS � inclure dans la page.
     *
     * @param string $placement filtre de placement � utiliser. Seuls les items dont l'attribut
     * "placement" correspond � la valeur indiqu�e seront retourn�e. La valeur "all" charge tous les
     * items sans condition.
     *
     * @return array un tableau d�crivant les items � inclure. Chaque �l�ment est un tableau ayant
     * les cl�s suivantes :
     * - placement
     * - condition
     * - charset
     * - href
     * - hreflang
     * - id
     * - media
     * - rel
     * - rev
     * - title
     * - type
     */
    public function getCss($placement='all')
    {
        return $this->getAssets
        (
            'css',
            $placement,
            array
            (
                'placement' => 'top',
                'condition' => '',
                'charset'   => '',
                'href'      => '',
                'hreflang'  => '',
                'id'        => '',
                'media'     => '',
                'rel'       => 'stylesheet',
                'rev'       => '',
                'title'     => '',
                'type'      => 'text/css',
            ),
            'href'
        );
    }


    /**
     * Retourne la liste des liens (balises link) � inclure dans la page.
     *
     * @param string $placement filtre de placement � utiliser. Seuls les items dont l'attribut
     * "placement" correspond � la valeur indiqu�e seront retourn�e. La valeur "all" charge tous les
     * items sans condition.
     *
     * @return array un tableau d�crivant les items � inclure. Chaque �l�ment est un tableau ayant
     * les cl�s suivantes :
     * - placement
     * - condition
     * - charset
     * - href
     * - hreflang
     * - id
     * - media
     * - rel
     * - rev
     * - title
     * - type
     */
    public function getLinks($placement='all')
    {
        return $this->getAssets
        (
            'links',
            $placement,
            array
            (
                'placement' => 'top',
                'condition' => '',
                'charset'   => '',
                'href'      => '',
                'hreflang'  => '',
                'id'        => '',
                'media'     => '',
                'rel'       => '',
                'rev'       => '',
                'title'     => '',
                'type'      => '',
            ),
            'href'
        );
    }


    /**
     * Retourne la liste des meta tags � inclure dans la page.
     *
     * @param string $placement filtre de placement � utiliser. Seuls les items dont l'attribut
     * "placement" correspond � la valeur indiqu�e seront retourn�e. La valeur "all" charge tous les
     * items sans condition.
     *
     * @return array un tableau d�crivant les items � inclure. Chaque �l�ment est un tableau ayant
     * les cl�s suivantes :
     * - placement
     * - condition
     * - name
     * - http-equiv
     * - content
     * - scheme
     */
    public function getMetas($placement='all')
    {
        return $this->getAssets
        (
            'metas',
            $placement,
            array
            (
                'placement'  => 'top',
                'condition'  => '',
                'name'       => '',
                'http-equiv' => '',
                'content'    => '',
                'scheme'     => '',
            ),
            'content'
        );
    }


    /**
     * Retourne le code html permettant d'inclure les fichiers havascript dont
     * la page a besoin.
     *
     * Typiquement, il s'agit du code html qui sera inclus dans la section
     * <code>head</code> de la page.
     *
     * Par d�faut, la m�thode construit une balise html
     * <code>script src="..."</code> pour chacun des fichiers indiqu�s dans la
     * cl� <code><js></code> de la configuration.
     *
     * Les modules descendants peuvent surcharger cette m�thode s'ils souhaitent
     * un autre comportement (exemple d'utilisation : utiliser une version
     * compress�e du script en mode production et la version normale sinon).
     *
     * @return string le code html � inclure.
     */
    public function GetJsLinks()
    {
        die("La m�thode getJsLinks() n'est plus support�e. A la place, utilisez &lt;scripts /&gt; dans vos templates.");
    }

    /**
     * Callback utilis� par l'ancien syst�me de layout (avec des $title, $CSS,
     * $JS et $content) dans le th�me.
     *
     * On conserve uniquement pour maintenir la compatibilit� ascendante.
     *
     * @deprecated
     * @param $name
     * @return unknown_type
     */
    public function layoutCallback($name)
    {
    	switch($name)
        {
        	case 'title':
                if (Template::$isCompiling) return true;

                return $this->getPageTitle();

        	case 'CSS':
        	    return $this->getCssLinks();

            case 'JS':
                return $this->GetJsLinks();

            case 'contents':
                if (Template::$isCompiling) return true;

                $this->runAction();
                return '';

        }
    }

    public function setLayout($path)
    {
        Config::set('layout', $path);
    }

//    // TODO: devrait pas �tre l�, etc.
//    private function convertCssOrJsPath($path, $defaultExtension, $defaultDir, $defaultSubDir)
//    {
//        // Si c'est une url absolue (http://xxx), on ne fait rien
//        if (substr($path, 0, 7)!='http://') // TODO: on a plusieurs fois des trucs comme �a dans le code, faire des fonctions
//        {
//
//            // Ajoute l'extension par d�faut des feuilles de style
//            $path=Utils::defaultExtension($path, $defaultExtension); // TODO: dans config
//
//            // Si c'est un chemin relatif, on cherche dans /web/styles
//            if (Utils::isRelativePath($path))
//            {
//                // Si on n'a pr�cis� que le nom ('styles'), m�me r�pertoire que le nom du th�me
//                if ($defaultSubDir != '' && dirname($path)=='.')
//                    $path="$defaultSubDir/$path";
//
////                $path = Runtime::$realHome . "$defaultDir/$path";
//                return Routing::linkFor("$defaultDir/$path");
//            }
//
//            // sinon (chemin absolu style '/xxx/yyy') on ajoute simplement $home
//            else
//            {
//                //$path=rtrim(Runtime::$realHome,'/') . $path;
//                return Routing::linkFor($path);
//            }
//        }
//        return $path;
//    }

}

/**
 * Exception de base g�n�r�e par Module
 *
 * @package     fab
 * @subpackage  module
 */
class ModuleException extends Exception
{
}

/**
 * Exception g�n�r�e par Module si le module � charger n'existe pas
 *
 * @package     fab
 * @subpackage  module
 */
class ModuleNotFoundException extends ModuleException
{
    public function __construct($module)
    {
        parent::__construct(sprintf('Le module %s n\'existe pas', $module));
    }
}

/**
 * Exception g�n�r�e par Module si l'action demand�e n'existe pas
 *
 * @package     fab
 * @subpackage  module
 */
class ModuleActionNotFoundException extends ModuleException
{
    public function __construct($module, $action)
    {
        parent::__construct(sprintf('L\'action %s du module %s n\'existe pas', $action, $module));
    }
}


?>