<?php
/**
 * @package     fab
 * @subpackage  template
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Template.php 1160 2010-05-07 07:32:45Z daniel.menard.bdsp $
 */


/*
 * TODO :
 * - FAIT, Youenn avoir l'�quivalent des walktables
 * - blocs <fill>/</fill>
 * - TableOfContents
 * - HandleLinks (conversion des liens en fonction des routes)
 * - Includes (un autre template)
 *
 */
/**
 * Gestionnaire de templates
 *
 * @package     fab
 * @subpackage  template
 */
class Template
{
    /**
     * options de configuration utilis�es :
     *
     * templates.forcecompile : indique s'il faut ou non forcer une recompilation
     * compl�te des templates, que ceux-ci aient �t� modifi�s ou non.
     *
     * templates.checktime : indique si le gestionnaire de template doit v�rifier
     * ou non si les templates ont �t� modifi�s depuis leur derni�re compilation.
     *
     * cache.enabled: indique si le cache est utilis� ou non. Si vous n'utilisez
     * pas le cache, les templates seront compil�s � chaque fois et aucun fichier
     * compil� ne sera cr��.
     */


    /**
     * @var array Pile utilis�e pour enregistrer l'�tat du gestionnaire de
     * templates et permettre � {@link run()} d'�tre r�entrante.
     */
    private static $stateStack=array();

    /**
     * @var array Tableau utilis� pour g�rer les blocs <opt> imbriqu�s (voir
     * {@link optBegin()} et {@link optEnd()})
     * @access private
     */
    private static $optFilled=array(0);

    /**
     * @var integer Indique le niveau d'imbrication des blocs <opt> (voir {@link
     * optBegin()} et {@link optEnd()})
     * @access private
     */
    private static $optLevel=0;

    /**
     * @var array Sources de donn�es pass�es en param�tre
     *
     * @access private
     */
    public static $data=null;

    /**
     * @var string Path complet du template en cours d'ex�cution.
     * Utilis� pour r�soudre les chemins relatifs (tables, sous-templates...)
     * @access private
     */
    private static $template='';

    public static $isCompiling=0;

    /**
     * constructeur
     *
     * Le constructeur est priv� : il n'est pas possible d'instancier la
     * classe. Utilisez directement les m�thodes statiques propos�es.
     */
    private function __construct()
    {
    }

    const PHP_START_TAG='<?php ';
    const PHP_END_TAG=" ?>";

    public static function getLevel()
    {
    	return count(self::$stateStack);
    }

    /**
     * TODO: doc obsol�te, � revoir
     * Ex�cute un template, en le recompilant au pr�alable si n�cessaire.
     *
     * La fonction run est r�entrante : on peut appeller run sur un template qui
     * lui m�me va appeller run pour un sous-template et ainsi de suite.
     *
     * @param string $template le nom ou le chemin, relatif ou absolu, du
     * template � ex�cuter. Si vous indiquez un chemin relatif, le template est
     * recherch� dans le r�pertoire du script appellant puis dans le r�pertoire
     * 'templates' de l'application et enfin dans le r�pertoire 'templates' du
     * framework.
     *
     * @param mixed $dataSources indique les sources de donn�es � utiliser
     * pour d�terminer la valeur des balises de champs pr�sentes dans le
     * template.
     *
     * Les gestionnaire de templates reconnait trois sources de donn�es :
     *
     * 1. Des fonctions de callback.  Une fonction de callback est une fonction
     * ou une m�thode qui prend en argument le nom du champ recherch� et
     * retourne une valeur. Si votre template contient une balise de champ
     * '[toto]', les fonctions de callback que vous indiquez seront appell� les
     * unes apr�s les autres jusqu'� ce que l'une d'entre elles retourne une
     * valeur.
     *
     * Lorsque vous indiquez une fonction callback, il peut s'agir :
     *
     * - d'une fonction globale : indiquez dans le tableau $dataSources une
     * chaine de caract�res contenant le nom de la fonction � appeller (exemple
     * : 'mycallback')
     *
     * - d'une m�thode statique de classe : indiquez dans le tableau
     * $dataSources soit une chaine de caract�res contenant le nom de la classe
     * suivi de '::' puis du nom de la m�thode statique � appeller (exemple :
     * 'Template:: postCallback') soit un tableau � deux �l�ments contenant �
     * l'index z�ro le nom de la classe et � l'index 1 le nom de la m�thode
     * statique � appeller (exemple : array ('Template', 'postCallback'))
     *
     * - d'une m�thode d'objet : indiquez dans le tableau $dataSources un
     * tableau � deux �l�ments contenant � l'index z�ro l'objet et � l'index 1
     * le nom de la m�thode � appeller (exemple : array ($this, 'postCallback'))
     *
     * 2. Des propri�t�s d'objets. Indiquez dans le tableau $dataSources l'objet
     * � utiliser. Si le gestionnaire de template rencontre une balise de champ
     * '[toto]' dans le template, il regardera si votre objet contient une
     * propri�t� nomm�e 'toto' et si c'est le cas utilisera la valeur obtenue.
     *
     * n.b. vous pouvez, dans votre objet, utiliser la m�thode magique '__get'
     * pour cr�er de pseudo propri�t�s.
     *
     * 3. Des valeurs : tous les �l�ments du tableau $dataSources dont la cl�
     * est alphanum�rique (i.e. ce n'est pas un simple index) sont consid�r�es
     * comme des valeurs.
     *
     * Si votre template contient une balise de champ '[toto]' et que vous avez
     * pass� comme �l�ment dans le tableau : 'toto'=>'xxx', c'est cette valeur
     * qui sera utilis�e.
     *
     * @param string $callbacks les nom des fonctions callback � utiliser pour
     * instancier le template. Vous pouvez indiquer une fonction unique ou
     * plusieurs en s�parant leurs noms par une virgule. Ce param�tre est
     * optionnel : si vous n'indiquez pas de fonctions callbacks, la fonction
     * essaiera d'utiliser une fonction dont le nom correspond au nom du
     * script appellant suffix� avec "_callback".
     */


    /**
     * Ex�cute un template
     *
     * @param string $path le nom du template (il peut s'agir du path du template s'il s'agit
     * d'un fichier ou d'un nom symbolique s'il s'agit d'un source g�n�r�)
     *
     * @param array|boolean les donn�es du template
     * @param string $source le source du template. (uniquement pour les templates g�n�r�s � la vol�e)
     * quand source est non null, on n'essaie pas de charger le fichier $path, on utilise dirtectement
     * le source
     */
    public static function runInternal($path, array $data, $source=null)
    {
//echo '<div style="background:#888;padding: 1em;border: 1px solid red;"><div>','TEMPLATE ', $path,'</div>';
        debug && Debug::log('Ex�cution du template %s', $path);

        // Sauvegarde l'�tat
        array_push
        (
            self::$stateStack,
            array
            (
                'template'      => self::$template,
                'data'          => self::$data
            )
        );

        // Stocke le path du template
        self::$template=$path;

        // Stocke les sources de donn�es pass�es en param�tre
        self::$data=$data;

        // Calcule la signature des sources de donn�es
        $signature='';
        foreach(self::$data as $data)
        {
            if (is_object($data))
                $signature.='o';
            elseif (is_string($data))
            {
                $signature.='f';
                if (! is_callable($data))
                    throw new Exception("fonction $data non trouv�e");
            }
            elseif (is_array($data))
            {
                if (is_callable($data))
                    $signature.='m';
                else
                    $signature.='a';
                    // TODO : les cl�s du tableau doivent �tre des chaines
            }
            else
                throw new Exception('Type de source de donn�es incorrecte : objet, tableau ou callback attendu');
        }

        // D�termine le path dans le cache du fichier
        $cachePath=Utils::setExtension($path, $signature . Utils::getExtension($path));

        // Compile le template s'il y a besoin
        if (self::needsCompilation($path, $cachePath))
        {
            timer && Timer::enter('Template.Compile ' . $path);
            // Charge le contenu du template
            if (is_null($source))
                if ( false === $source=file_get_contents($path) )
                    throw new Exception("Le template '$template' est introuvable.");

            // Compile le code
//            require_once dirname(__FILE__) . '/TemplateCompiler.php';
            $source=TemplateCompiler::compile($source, self::$data);

//          if (php_version < 6) ou  if (! PHP_IS_UTF8)
            $source=utf8_decode($source);

            // Stocke le template dans le cache et l'ex�cute
            if (config::get('cache.enabled'))
            {
                debug && Debug::log("Mise en cache de '%s'", $path);
                Cache::set($cachePath, $source);
                debug && Debug::log("Ex�cution � partir du cache");
                timer && Timer::leave();
                require(Cache::getPath($cachePath));
            }
            else
            {
                timer && Timer::leave();
                debug && Debug::log("Cache d�sactiv�, evaluation du template compil�");
                eval(self::PHP_END_TAG . $source);
            }

        }

        // Sinon, ex�cute le template � partir du cache
        else
        {
            debug && Debug::log("Ex�cution � partir du cache");
            require(Cache::getPath($cachePath));
        }

        // restaure l'�tat du gestionnaire
        $t=array_pop(self::$stateStack);
        self::$template         =$t['template'];
        self::$data             =$t['data'];
//echo '</div>';
    }

    public static function run($path /* $dataSource1, $dataSource2, ..., $dataSourceN */ )
    {
        timer && Timer::enter('Template.run '.$path);
        // R�sout le path s'il est relatif
        if (Utils::isRelativePath($path))
        {
            $sav=$path;
            if (false === $path=Utils::searchFile($path))
                throw new Exception("Impossible de trouver le template $sav. searchPath=".print_r(Utils::$searchPath, true));
        }

        // Cr�e un tableau � partir des sources de donn�es pass�es en param�tre
        $data=func_get_args();
        array_shift($data);

        // Ajoute une source '$this' correspondant au module appellant
        array_unshift($data,array('this'=>Utils::callerObject(2)));

        // Ex�cute le template
        self::runInternal($path,$data);
        timer && Timer::leave();
    }

    public static function runSource($path, $source /* $dataSource1, $dataSource2, ..., $dataSourceN */ )
    {
        // D�termine le path du r�pertoire du script qui nous appelle
        //$path=dirname(Utils::callerScript()).DIRECTORY_SEPARATOR;
        // TODO: +num�ro de ligne ou nom de la fonction ?

        // Cr�e un tableau � partir des sources de donn�es pass�es en param�tre
        $data=func_get_args();
        array_shift($data); // enl�ve $path de la liste
        array_shift($data); // enl�ve $source de la liste

        // Ajoute une source '$this' correspondant au module appellant
        array_unshift($data,array('this'=>Utils::callerObject(2)));

        // Ex�cute le template
        self::runInternal($path,$data,$source);
    }

    /**
     * Teste si un template a besoin d'�tre recompil� en comparant la version
     * en cache avec la version source.
     *
     * La fonction prend �galement en compte les options templates.forcecompile
     * et templates.checkTime
     *
     * @param string $template path du template � v�rifier
     * @param string autres si le template d�pend d'autres fichiers, vous pouvez
     * les indiquer.
     *
     * @return boolean vrai si le template doit �tre recompil�, false sinon.
     */
    private static function needsCompilation($path, $cachePath)
    {
        // Si templates.forceCompile est � true, on recompile syst�matiquement
        if (Config::get('templates.forcecompile')) return true;

        // Si le cache est d�sactiv�, on recompile syst�matiquement
        if (! Config::get('cache.enabled')) return true;

        // si le fichier n'est pas encore dans le cache, il faut le g�n�rer
        $mtime=Cache::lastModified($cachePath);
        if ( $mtime==0 ) return true;

        // Si templates.checktime est � false, termin�
        if (! Config::get('templates.checktime')) return false;

        // Compare la date du fichier en cache avec celle du fichier original
        if ($mtime<=@filemtime($path) ) return true;

        // explication du '@' ci-dessus :
        // si le fichier n'existe pas (utilisation de runSource, par exemple)
        // �vite de g�n�rer un warning et retourne false (ie pas besoin de
        // recompiler)

        // Le fichier est dans le cache et il est � jour
        return false;
    }

    /**
     * Fonction appell�e au d�but d'un bloc <opt>
     * @internal cette fonction ne doit �tre appell�e que depuis un template.
     * @access public
     */
    public static function optBegin()
    {
        self::$optFilled[++self::$optLevel]=0;
        ob_start();
    }

    /**
     * Fonction appell�e � la fin d'un bloc <opt>
     * @internal cette fonction ne doit �tre appell�e que depuis un template.
     * @access public
     */
    public static function optEnd($minimum=1)
    {
        // Si on a rencontr� au moins un champ non vide
        if (self::$optFilled[self::$optLevel--]>=$minimum)
        {
            // Indique � l'�ventuel bloc opt parent qu'on est renseign�
            self::$optFilled[self::$optLevel]++ ;

            // Envoit le contenu du bloc
            ob_end_flush();
        }

        // Sinon, vide le contenu du bloc
        else
        {
            ob_end_clean();
        }
    }

    /**
     * Examine la valeur pass�e en param�tre et marque le bloc opt en cours comme
     * "rempli" si la valeur est renseign�e.
     *
     * La valeur est consid�r�e comme vide si :
     * - c'est la valeur 'null'
     * - c'est une chaine de caract�res vide
     * - c'est un tableau ne contenant aucun �l�ment
     *
     * Elle est comme renseign�e si :
     * - c'est une chaine non vide
     * - c'est un entier ou un r�el (m�me si c'est la valeur z�ro)
     * - c'est un bool�en (m�me si c'est la valeur false)
     * - c'est un tableau non vide
     * - un objet, une ressource, etc.
     *
     * @param mixed $x la valeur � examiner
     * @return mixed la valeur pass�e en param�tre
     */
    public static function filled($x)
    {
        // Cas o� $x est consid�r� comme non rempli
        if (is_null($x)) return $x;
        if ($x==='') return $x;
        if (is_array($x) && count($x)===0) return $x;

        // Dans tous les autres cas, consid�r� comme renseign�
        // int ou float (y compris 0), array non vide, bool (y compris false)
        self::$optFilled[self::$optLevel]++;
    	return $x;
    }

    /**
     * Construit la liste des valeurs qui est utilis�e par un bloc <fill>...</fill>
     *
     * @param string|array $values les valeurs provenant du champ
     * @param boolean $strict true pour que le fill tiennent compte des accents
     * et de la casse des caract�res, false (valeur par d�faut) sinon.
     * @return array un tableau dont les cl�s contiennent les articles trouv�s
     * dans $values et dont la valeur est true.
     */
    public static function getFillValues($values, $strict=false)
    {
        if (! is_array($values))
            $values=preg_split('~\s*[,;/��|]\s*~', trim($values), -1, PREG_SPLIT_NO_EMPTY);
        // autres candidats possibles comme s�parateurs utilis�s dans le preg_split ci-dessus : cr, lf, tilde

        $save=$values;

        if (! $strict)
        {
            foreach($values as & $value)
                $value=implode(' ', Utils::tokenize($value));
        }

        if (count($values))
            $values=array_combine($values, $save);

        return $values;
    }

/*
runSlot examine la config en cours pour savoir s'il faut examiner le noeud ou pas.
si enabled=false : return false (ne pas ex�cuter le slot, ne pas afficher le contenu par d�faut)
si file="" et action="" return true (afficher le contenu par d�faut du slot)
si file : Template::Run(file, currentdatasources)
sinonsi action : Routing::dispatch(action, currentdatasource)
runSlot retourne true s'il faut afficher le contenu par d�faut du noeud
return false (ne pas afficher le contenu par d�faut)
*/
    public static function runSlot($name, $defaultAction='', array $args=null)
    {
        $action=Config::get("slots.$name", $defaultAction);

        if ($action==='') return true;

        if ($action==='none') return false;

        debug && Debug::log('slot %s : %s', $name, $action);

        // S'il s'agit d'une action, on l'ex�cute
        if ($action[0]==='/')
        {
            $action=ltrim($action, '/');
            $module=strtok($action, '/');
            $action=strtok('/');
            if ($action==='') $action='Index';

            // On repart de la requ�te d'origine, et on ajoute les nouveaux param�tres
            $request=Runtime::$request->copy();
            if (! is_null($args)) $request->addParameters($args);

            $sav=Runtime::$request->getModule($module);
            Runtime::$request->setModule($module);
            $savConfig=Config::getAll();
            Config::clear();
            Config::addArray(Runtime::$baseConfig);
            Module::runAs($request, $module, $action, true);
            Config::clear();
            Config::addArray($savConfig);
            Runtime::$request->setModule($sav);
        }

        // C'est un template : on l'ex�cute
        else
        {
            $path=$action;

            // R�sout le path s'il est relatif
            if (Utils::isRelativePath($action))
            {
                if (false === $path=Utils::searchFile($path, Runtime::$root))   // root : permet que le template soit relatif � la racine du site (exemple : wordpress)
                    throw new Exception("Impossible de trouver le template $action. searchPath=".print_r(Utils::$searchPath, true));
            }

            // Ajoute les arguments pass�s en param�tre aux sources de donn�es en cours.
            // Le premier �l�ment de self::$data est un tableau qui contient $this
            // (et �ventuellement d'autres donn�es).
            // On fusionne les arguments pass�s au slot dans ce tableau. Le slot a ainsi
            // acc�s � toutes les donn�es de ses "anc�tres", mais les donn�es sp�cifiques
            // qu'on lui a pass� sont prioritaires.
            // En utilisant array_merge(), si une m�me cl� existe � la fois dans $args
            // et dans data[0], la valeur pr�sente dans $args �crasera la valeur existante
            // (cas d'un slot r�cursive comme pour les localisations emploi bdsp).
            $data=self::$data;
            if (!is_null($args)) $data[0] = array_merge($data[0], $args);

            // Ex�cute le template
            self::runInternal($path,$data);
        }
        return false;
    }

    private static $lastId='';
    private static $usedId=array();

    public static function autoId($name='id')
    {
        $name=Utils::convertString($name, 'ident');
        $name=implode('_', str_word_count($name, 1, '0123456789'));

        if (isset(self::$usedId[$name]))
            $name=$name.(++self::$usedId[$name]);
        else
            self::$usedId[$name]=1;

        return self::$lastId=$name;
    }

    public static function lastId()
    {
        return self::$lastId;
    }
}