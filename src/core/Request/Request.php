<?php
/**
 * @package     fab
 * @subpackage  request
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Request.php 1077 2010-01-05 16:28:37Z daniel.menard.bdsp $
 */

/**
 * Une requ�te repr�sentant l'environnement et les param�tres de l'action
 * qui sera ex�cut�e.
 *
 * L'objet Request est destin� � �viter que les tableaux $_GET, $_POST, etc.
 * ne soient acc�d�s directement.
 *
 * Certaines m�thodes retourne $this pour permettre de chainer les appels de
 * m�thodes :
 *
 * <code>
 * $request
 *     ->setModule('thesaurus')
 *     ->setAction('search')
 *     ->set('query', 'health');
 * </code>
 *
 * Request propose �galement des m�thodes (chain�es) permettant de valider
 * ais�ment les param�tres de la requ�te :
 *
 * <code>
 * $request
 *     ->required('ref')
 *     ->int()
 *     ->unique()
 *     ->min(1);
 * </code>
 *
 * @package     fab
 * @subpackage  request
 */
class Request
{
    /**
     * Les param�tres de la requ�te
     *
     * @var array
     */
    private $_parameters=array();

    /**
     * Le nom exact du module auquel est destin�e cette requ�te ou null
     * si la requ�te n'a pas encore �t� rout�e
     *
     * @var string|null
     */
    private $_module=null;

    /**
     * Le nom exact de l'action � laquelle est destin�e cette requ�te ou null
     * si la requ�te n'a pas encore �t� rout�e
     *
     * @var string|null
     */
    private $_action=null;

    /**
     * Nom de la variable en cours de validation
     *
     * @var string
     */
    private $_checkName;

    /**
     * Valeur actuelle de la variable en cours de validation
     *
     * @var mixed
     */
    private $_check;

    /**
     * Construit un nouvel objet Request avec les param�tres indiqu�s.
     *
     * Des param�tres suppl�mentaires peuvent �tre ajout�s � la requ�te
     * en utilisant {@link set()} et {@link add()}
     *
     * @param array $parameters ... des tableaux contenant les param�tres
     * initiaux de la requ�te.
     */
    public function __construct(array $parameters=array())
    {
        $this->_parameters=$parameters;

        $args=func_get_args();
        array_shift($args);
        foreach($args as $arg)
        {
            $this->addParameters($arg);
        }
    }

    /**
     * M�thode statique permettant de cr�er un nouvel objet Request avec les
     * param�tres indiqu�s.
     *
     * Php ne permet pas de chainer des m�thodes apr�s un appel � new :
     * <code>$request=new Request()->setAction('/');</code> g�n�re une erreur.
     *
     * La m�thode statique create permet de contourner le probl�me en �crivant :
     * <code>$request=Request::create()->setAction('/');</code>
     *
     * @param array $parameters ... des tableaux contenant les param�tres
     * initiaux de la requ�te.
     */
    public static function create(array $parameters=array())
    {
        $request=new Request();
        $args=func_get_args();
        foreach($args as $arg)
        {
            $request->addParameters($arg);
        }
        return $request;
    }

    /**
     * Clone la requ�te en cours
     *
     * @return Request
     */
    public function copy()
    {
        return clone $this;
    }

    /**
     * Ajoute un tableau de param�tres � la requ�te
     *
     * @param array $parameters
     * @return Request $this pour permettre le chainage des appels de m�thodes
     */
    public function addParameters(array $parameters)
    {
        foreach($parameters as $key=>$value)
        {
            // Si la cl� n'existe pas d�j�, on l'ins�re � la fin du tableau
            if (!array_key_exists($key, $this->_parameters))
            {
                $this->_parameters[$key]=$value;
                continue;
            }

            // Existe d�j�, c'est un tableau, ajoute la valeur � la fin
            if (is_array($this->_parameters[$key]))
            {
                // tableau + tableau
                if (is_array($value))
                    $this->_parameters[$key]=array_merge($this->_parameters[$key], $value);

                // tableau + valeur
                else
                    $this->_parameters[$key][]=$value;
            }

            // Existe d�j�, simple valeur, cr�e un tableau contenant la valeur existante et la valeur indiqu�e
            else
            {
                // valeur + tableau
                if (is_array($value))
                    $this->_parameters[$key]=array_merge(array($this->_parameters[$key]), $value);

                // valeur + valeur
                else
                    $this->_parameters[$key]=array($this->_parameters[$key], $value);
            }
        }
        return $this;
    }

    /**
     * Retourne le nom exact du module auquel est destin� la requ�te ou null
     * si la requ�te n'a pas encore �t� rout�e
     *
     * @return string|null
     */
    public function getModule()
    {
        return $this->_module;
    }


    /**
     * Modifie le nom du module auquel est destin�e la requ�te
     *
     * @param string $module
     * @return Request $this pour permettre le chainage des appels de m�thodes
     */
    public function setModule($module=null)
    {
        $this->_module=$module;
        return $this;
    }


    /**
     * Retourne le nom exact de l'action � laquelle est destin�e la requ�te ou
     * null si la requ�te n'a pas encore �t� rout�e
     *
     * @return string|null
     */
    public function getAction()
    {
        return $this->_action;
    }


    /**
     * Modifie le nom de l'action � laquelle est destin�e la requ�te
     *
     * @param string $action la nouvelle action de la requ�te
     * @return Request $this pour permettre le chainage des appels de m�thodes
     */
    public function setAction($action=null)
    {
        $this->_action=$action;
        return $this;
    }


    /**
     * Retourne la valeur du param�tre indiqu� ou null si le nom indiqu� ne
     * figure pas dans les param�tres de la requ�te.
     *
     * __get est une m�thode magique de php qui permet d'acc�der aux param�tres
     * de la requ�te comme s'il s'agissait de propri�t�s de l'objet Request
     * (par exemple <code>$request->item</code>)
     *
     * La m�thode {@link get()} est similaire mais permet d'indiquer une valeur
     * par d�faut.
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        if (array_key_exists($key, $this->_parameters))
            return $this->_parameters[$key];
        return null;
    }


    /**
     * Retourne la valeur du param�tre indiqu� ou la valeur par d�faut sp�cifi�e
     * si le nom indiqu� ne figure pas dans les param�tres de la requ�te.
     *
     * get est similaire � {@link __get()} mais permet d'indiquer une valeur par
     * d�faut (par exemple <code>$request->get('item', 'abc')</code>)
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default=null)
    {
        if (is_null($value=$this->__get($key)))
            return $default;
        else
            return $value;
    }


    /**
     * Modifie la valeur du param�tre indiqu�.
     *
     * __set est une m�thode magique de php qui permet de modifier les
     * param�tres de la requ�te comme s'il s'agissait de propri�t�s de
     * l'objet Request (par exemple <code>$request->item = 12</code>)
     *
     * Set remplace compl�tement la valeur existante. Pour ajouter une valeur
     * � un param�tre existant, utiliser {@link add()}
     *
     * @param string $key
     * @param mixed $value
     */
    public function __set($key, $value)
    {
        $this->_parameters[$key]=$value;
    }


    /**
     * Modifie la valeur du param�tre indiqu� ou celle du param�tre en cours de
     * validation.
     *
     * Exemples :
     * <code>
     * $request->set('item', 12);
     * $request->unique('REF')->int()->min(1)->set()
     * </code>
     * @param string $key
     * @param mixed $value
     * @return Request $this pour permettre le chainage des appels de m�thodes
     */
    public function set($key=null, $value=null)
    {
        if (is_null($key))
        {
            $this->_parameters[$this->_checkName]=$this->_check;
            $this->_checkName=null;
        }
        else
        {
            $this->__set($key, $value);
        }
        return $this;
    }

    /**
     * Supprime tous les param�tres de la requ�te sauf ceux dont le nom est
     * indiqu� en param�tre.
     *
     * Exemple :
     * <code>
     * $request->keepOnly('REF'); // supprime tous sauf REF
     * </code>
     *
     * @param string $arg nom du premier param�tre � conserver. Vous pouvez
     * indiquer autant d'argument arg que n�cessaire
     * @return Request $this pour permettre le chainage des appels de m�thodes
     */
    public function keepOnly($arg)
    {
        $args=func_get_args();
        $this->_parameters=array_intersect_key($this->_parameters, array_flip($args));
        return $this;
    }

    /**
     * Supprime le param�tre indiqu�
     *
     * __unset est une m�thode magique de php qui permet de supprimer les
     * param�tres de la requ�te comme s'il s'agissait de propri�t�s de
     * l'objet Request (par exemple <code>unset($request->item)</code>)
     *
     * @param string $key
     */
    public function __unset($key)
    {
        unset($this->_parameters[$key]);
    }


    /**
     * Supprime un param�tre de la requ�te.
     *
     * Exemples :
     * - <code>$request->clear('item')</code> // supprime le param�tre
     *   item de la requ�te ;
     * - <code>$request->clear('item', 'article')</code> // supprime la
     *   valeur 'article' du param�tre item de la requ�te.
     *
     * @param string $key le nom du param�tre � supprimer.
     * @param mixed $value optionnel : la valeur � effacer.
     * Par d�faut (lorsque $value n'est pas indiqu�, clear efface compl�tement
     * le param�tre indiqu� par $key. Si $value est indiqu� et que $key d�signe
     * un tableau, seule la valeur indiqu�e va �tre supprim�e de la requ�te.
     * Si $key d�signe un scalaire, le param�tre ne sera supprim� que si la valeur
     * associ�e correspond � $value.
     *
     * @return Request $this pour permettre le chainage des appels de m�thodes
     *
     * @todo : accepter plusieurs param�tres pour permettre de vider
     * plusieurs arguments d'un coup
     */
    public function clear($key=null, $value=null)
    {
        if (is_null($key))
        {
            $this->_parameters=array();
        }
        else
        {
            if (is_null($value))
            {
                unset($this->_parameters[$key]);
            }
            else
            {
                if (array_key_exists($key, $this->_parameters))
                {
                    $v=$this->_parameters[$key];
                    if (is_scalar($v))
                    {
                        if ($v === $value)
                            unset($this->_parameters[$key]);
                    }
                    else
                    {
                        foreach($this->_parameters[$key] as $k=>$v)
                            if ($v === $value) unset($this->_parameters[$key][$k]);
                        if (empty($this->_parameters[$key]))
                            unset($this->_parameters[$key]);
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Supprime de la requ�te tous les param�tres dont la valeur est
     * une chaine vide, un tableau vide ou la valeur null.
     *
     * @return Request $this pour permettre le chainage des appels de m�thodes
     */
    public function clearNull()
    {
        foreach($this->_parameters as $key=>&$value)
        {
            if ($value===null or $value==='' or $value===array())
            {
                unset($this->_parameters[$key]);
            }
            elseif(is_array($value))
            {
                foreach($value as $key=>$item)
                {
                    if ($item===null or $item==='' or $item===array())
                    {
                        unset($value[$key]);
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Indique si la requ�te contient des param�tres
     *
     * @return bool
     */
    public function hasParameters()
    {
        return count($this->_parameters)!==0;
    }


    /**
     * Retourne tous les param�tres pr�sents dans la requ�te
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->_parameters;
    }


    /**
     * D�termine si le param�tre indiqu� est d�fini dans la requ�te
     *
     * __isset() est une m�thode magique de php qui permet de tester l'existence
     * d'un param�tre comme s'il s'agissait d'une propri�t� de l'objet Request.
     *
     * La fonction {@link has()} fait la m�me chose mais prend le nom de
     * l'argument en param�tre.
     *
     * @param string $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->_parameters[$key]);
    }

    /**
     * D�termine si le param�tre indiqu� existe dans la requ�te.
     *
     * La fonction retourne true m�me si le param�tre � la valeur null
     *
     * @param string $key le nom du param�tre � tester.
     * @param mixed $value optionnel, la valeur � tester. Lorsque $value
     * est indiqu�e, la m�thode retourne true si le param�tre $key figure
     * dans la requ�te et s'il contient la valeur $value.
     *
     * @return bool
     */
    public function has($key, $value=null)
    {
        if (! array_key_exists($key, $this->_parameters)) return false;
        if (is_null($value)) return true;

        foreach((array) $this->_parameters[$key] as $v)
            if ($v === $value) return true;
        return false;
    }


    /**
     * Ajoute une valeur au param�tre indiqu�
     *
     * Add ajoute le param�tre indiqu� � la liste des param�tres de la requ�te.
     * Si le param�tre indiqu� existait d�j�, la valeur existante est transform�e
     * en tableau et la valeur indiqu�e est ajout�e au tableau obtenu.
     *
     * Pour remplacer compl�tement la valeur d'un param�tre existant, utiliser
     * {@link set()}
     *
     * @param string $key
     * @param mixed $value
     * @return Request $this pour permettre le chainage des appels de m�thodes
     */
    public function add($key, $value)
    {
        // Si la cl� n'existe pas d�j�, on l'ins�re � la fin du tableau
        if (!array_key_exists($key, $this->_parameters))
        {
            $this->_parameters[$key]=$value;
            return $this;
        }

        // La cl� existe d�j�
        $item=& $this->_parameters[$key];

        // Si c'est d�j� un tableau, ajoute la valeur � la fin du tableau
        if (is_array($item))
            $item[]=$value;

        // Sinon, cr�e un tableau contenant la valeur existante et la valeur indiqu�e
        else
            $item=array($item, $value);

        return $this;
    }


    /**
     * Retourne la valeur d'un param�tre figurant dans un autre tableau
     * que {@link _parameters} ou la valeur par d�faut indiqu�e
     *
     * @param array $array le tableau dans lequel la cl� indiqu�e va �tre
     * recherch�e
     * @param string $key la cl� � rechercher
     * @param mixed $default la valeur par d�faut � retourner si $key ne figure
     * pas dans le tableau $array
     * @return string|null
     */
    protected function other($array, $key, $default=null)
    {
        if (array_key_exists($key, $array))
            return $array[$key];
        return $default;
    }


    /**
     * Retourne la valeur d'une variable du server
     *
     * @param string $key
     * @param mixed $default
     * @return string|null
     */
    public function server($key, $default=null)
    {
        return $this->other($_SERVER, $key, $default);
    }


    /**
     * Retourne la valeur d'une variable d'environnement
     *
     * @param string $key
     * @param mixed $default
     * @return string|null
     */
    public function env($key, $default=null)
    {
        return $this->other($_ENV, $key, $default);
    }


    /**
     * Retourne la valeur d'un cookie
     *
     * @param string $key
     * @param mixed $default
     * @return string|null
     */
    public function cookie($key, $default=null)
    {
        return $this->other($_COOKIE, $key, $default);
    }


    /**
     * D�termine si la requ�te en cours est une requ�te ajax ou non.
     *
     * La d�tection est bas�e sur la pr�sence ou non de l'ent�te http
     * <code>X_REQUESTED_WITH</code> qui est ajout� � la requ�te http par
     * les librairies ajax les plus courante (cas de prototype, jquery, YUI,
     * mais pas de dojo).
     *
     * @return bool true si la requ�te http contient un ent�te
     * <code>X_REQUESTED_WITH</code> contenant la valeur
     * <code>XMLHttpRequest</code> (sensible � la casse)
     */
    public function isAjax()
    {
        return ($this->server('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest');
    }


    /**
     * Fonction ex�cut�e � chaque fois qu'une fonction de validation est appell�e.
     *
     * Si $key est non null, une nouvelle validation commence et la fonction
     * retourne la valeur de ce param�tre.
     *
     * Si $ref est null, la validation en cours continue et la fonction retourne
     * la valeur actuelle du param�tre en cours.
     *
     * Une exception est g�n�r�e si check est appell�e avec $key==null et qu'il
     * n'y a pas de validation en cours et si check() est appell�e avec un nom
     * alors qu'il y a d�j� une validation en cours.
     *
     * @param string|null $key
     * @return mixed
     */
    private function check($key=null)
    {
        if (is_null($key))
        {
            if (is_null($this->_checkName))
            {
                $stack=debug_backtrace();
                throw new BadMethodCallException(sprintf('%s::%s() appell�e sans indiquer le nom du param�tre � v�rifier', __CLASS__, $stack[1]['function']));
            }
        }
        else
        {
            if (!is_null($this->_checkName))
            {
                $stack=debug_backtrace();
                throw new BadMethodCallException(sprintf('Appel de %s::%s("%s") alors que "%s" est d�j� en cours de validation. Oubli de ok() au dernier appel ?' , __CLASS__, $stack[1]['function'], $key, $this->_checkName));
            }
            $this->_check=$this->get($key);
            $this->_checkName=$key;
        }
        return $this->_check;
    }


    /**
     * Validation : v�rifie un param�tre requis
     *
     * Le param�tre est consid�r� comme 'absent' si sa valeur est null, un
     * tableau vide, une chaine vide ou une chaine ne contenant que des blancs.
     *
     * Si la valeur du param�tre est un tableau, le test est appliqu� � chacun
     * des �l�ments du tableau.
     *
     * La valeur actuelle du param�tre en cours de validation n'est pas modifi�e
     * par le test.
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $request->required('item')->ok();
     * $request->bool('item')->required()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Request $this pour permettre le chainage des appels de m�thodes
     * @throws RequestParameterRequired si le test �choue
     */
    public function required($key=null)
    {
        $value=(array)$this->check($key);

        if (count($value)===0)
        {
            throw new RequestParameterRequired($this->_checkName);
        }

        foreach((array)$value as $value)
        {
            if (is_null($value) || (is_string($value) && trim($value)===''))
            {
                throw new RequestParameterRequired($this->_checkName);
            }
        }

        return $this;
    }

    /**
     * Validation : d�finit la valeur par d�faut d'un param�tre de la requ�te
     *
     * @param string|null $key
     * @param scalar $default
     *
     * @return Request $this pour permettre le chainage des appels de m�thodes
     */
    public function defaults($key, $default=null)
    {
        if (is_null($default))
        {
            $default=$key;
            $key=null;
        }
        $value=$this->check($key);

        if (is_null($value) || $value==='')
        {
            $this->_check=$default;
        }

        return $this;
    }


    /**
     * Validation : v�rifie un bool�en
     *
     * bool() reconna�t les bool�ens mais aussi les chaines
     * <code>'true','on', '1' </code> et <code>'false','off','0'</code>
     * (quelque soit la casse et les espaces de d�but ou de fin �ventuels).
     *
     * Si la valeur du param�tre est un tableau, le test est appliqu� � chacun
     * des �l�ments du tableau.
     *
     * A l'issue du test, la valeur du param�tre en cours de validation est
     * toujours un bool�en ou un tableau de bool�ens
     *
     * Exemples d'utilisation :
     * <code>
     * $request->bool('flag')->ok();
     * $request->required('flag')->bool()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Request $this pour permettre le chainage des appels de m�thodes
     *
     * @throws RequestParameterBoolExpected si le test �choue
     */
    public function bool($key=null)
    {
        $this->check($key);
        foreach((array)$this->_check as $i=>$value)
        {
            if (is_bool($value))
            {
                // ok
            }
            elseif (is_int($value) && ($value===0 || $value===1))
            {
                $value=(bool)$value;
            }
            elseif (is_string($value))
            {
                $t=array('true'=>true, 'false'=>false, 'on'=>true, 'off'=>false, '1'=>true, '0'=>false);
                $key=strtolower(trim($value));
                if (array_key_exists($key,$t))
                {
                    $value=$t[$key];
                }
                else
                {
                    throw new RequestParameterBoolExpected($this->_checkName, $value);
                }
            }
            else
            {
                throw new RequestParameterBoolExpected($this->_checkName, $value);
            }
            if (is_array($this->_check))
            {
                $this->_check[$i]=$value;
            }
            else
            {
                $this->_check=$value;
            }
        }
        return $this;
    }


    /**
     * Validation : v�rifie un entier
     *
     * int() reconna�t les entiers mais aussi les chaines repr�sentant un entier
     * (les espaces de d�but ou de fin �ventuels sont ignor�s).
     *
     * Si la valeur du param�tre est un tableau, le test est appliqu� � chacun
     * des �l�ments du tableau.
     *
     * A l'issue du test, la valeur du param�tre en cours de validation est
     * toujours un entier ou un tableau d'entiers.
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $request->int('nb')->ok();
     * $request->required('nb')->int()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Request $this pour permettre le chainage des appels de m�thodes
     *
     * @throws RequestParameterIntExpected si le test �choue
     */
    public function int($key=null)
    {
        $this->check($key);

        foreach((array)$this->_check as $i=>$value)
        {
            if (is_int($value))
            {
            }
            elseif (is_string($value) && ctype_digit(ltrim(rtrim($value), '+- ')))
            {
                $value=(int)$value;
            }
            elseif (is_float($value) && (round($value,0)==$value) && ($value > -PHP_INT_MAX-1) && ($value < PHP_INT_MAX))
            {
                $value=(int)$value;
            }
            else
            {
                throw new RequestParameterIntExpected($this->_checkName, $value);
            }

            if (is_array($this->_check))
            {
                $this->_check[$i]=$value;
            }
            else
            {
                $this->_check=$value;
            }
        }
        return $this;
    }

    /* todo:
    public function float($key)
    {
        return $this;

    }
    public function match($key, $regexp)
    {
        return $this;

    }

    public function convert($key, array(oldvalues=>newvalues))
    {
        return $this;

    }

    upper()
    lower()

    */


    /**
     * Validation : v�rifie qu'un param�tre est sup�rieur ou �gal au minimum
     * autoris�
     *
     * Si la valeur du param�tre est un tableau, le test est appliqu� � chacun
     * des �l�ments du tableau.
     *
     * A l'issue du test, la valeur du param�tre en cours de validation est
     * toujours du m�me type que l'argument $min indiqu�.
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $request->min('nb',5)->ok();
     * $request->min('author','azimov')->ok();
     * $request->required('nb')->min(5)->ok();
     * </code>
     *
     * @param string|null $key
     * @param scalar $min
     * @return Request $this pour permettre le chainage des appels de m�thodes
     *
     * @throws RequestParameterMinExpected si le test �choue
     */
    public function min($key, $min=null)
    {
        if (is_null($min))
        {
            $min=$key;
            $key=null;
        }

        $this->check($key);

        foreach((array)$this->_check as $i=>$value)
        {
            if (is_array($this->_check))
            {
                settype($this->_check[$i], gettype($min));
            }
            else
            {
                settype($this->_check, gettype($min));
            }
            if ($value >= $min)
            {
                continue;
            }
            throw new RequestParameterMinExpected($this->_checkName, $value, $min);
        }

        return $this;
    }


    /**
     * Validation : v�rifie qu'un param�tre est inf�rieur ou �gal au maximum
     * autoris�
     *
     * Si la valeur du param�tre est un tableau, le test est appliqu� � chacun
     * des �l�ments du tableau.
     *
     * A l'issue du test, la valeur du param�tre en cours de validation est
     * toujours du m�me type que l'argument $max indiqu�.
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $request->max('nb',20)->ok();
     * $request->max('author','bradbury')->ok();
     * $request->required('nb')->max(20)->ok();
     * </code>
     *
     * @param string|null $key
     * @param scalar $max
     *
     * @return Request $this pour permettre le chainage des appels de m�thodes
     *
     * @throws RequestParameterMaxExpected si le test �choue
     */
    public function max($key, $max=null)
    {
        if (is_null($max))
        {
            $max=$key;
            $key=null;
        }
        $this->check($key);
        foreach((array)$this->_check as $i=>$value)
        {
            if (is_array($this->_check))
            {
                settype($this->_check[$i], gettype($max));
            }
            else
            {
                settype($this->_check, gettype($max));
            }

            if ($value <= $max)
            {
                continue;
            }

            throw new RequestParameterMaxExpected($this->_checkName, $value, $max);
        }
        return $this;
    }


    /**
     * Validation : v�rifie qu'un param�tre contient l'une des valeurs autoris�es
     *
     * Si la valeur du param�tre est un tableau, le test est appliqu� � chacun
     * des �l�ments du tableau.
     *
     * Si les valeurs autoris�es sont des chaines, la casse des caract�res et
     * les �ventuels espaces de d�but et de fin sont ignor�s.
     *
     * A l'issue du test, la valeur du param�tre en cours de validation est
     * toujours strictement identique � l'une des valeurs autoris�es.
     *
     * Vous pouvez appeller de deux fa�ons diff�rentes :
     * - soit en indiquant les valeurs autoris�es en param�tres
     * - soit en passant un param�tre unique de type tableau contenant les
     *   diff�rentes valeurs autoris�es.
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $request->oneof('nb',2,4,6)->ok();
     * $request->oneof('nb',array(2,4,6))->ok(); // valeurs autoris�es sous forme de tableau
     * $request->oneof('author', 'azimov', 'bradbury')->ok();
     * $request->required('nb')->oneof(2,4,6)->ok();
     * </code>
     *
     * @param string|array|null $key
     * @return Request $this pour permettre le chainage des appels de m�thodes
     *
     * @throws RequestParameterBadValue si le test �choue
     */
    public function oneof($key)
    {
        $values=func_get_args();
        if (is_null($this->_checkName))
        {
            $key=array_shift($values);
        }
        else
        {
            $key=null;
        }

        $this->check($key);

        if (count($values) === 1 && is_array(reset($values)))
            $values = array_shift($values);

        foreach((array)$this->_check as $i=>$value)
        {
            foreach($values as $allowed)
            {
                if ($allowed===$value || (is_string($allowed) && strtolower(trim($allowed))===strtolower(trim($value))))
                {
                    if (is_array($this->_check))
                    {
                        $this->_check[$i]=$allowed;
                    }
                    else
                    {
                        $this->_check=$allowed;
                    }

                    continue 2;
                }
            }
            throw new RequestParameterBadValue($this->_checkName, $value);
        }
        return $this;
    }


    /**
     * Validation : v�rifie qu'un param�tre contient l'une des valeurs autoris�es
     * et la convertit vers une valeur de r�f�rence.
     *
     * Si la valeur du param�tre est un tableau, le test est appliqu� � chacun
     * des �l�ments du tableau.
     *
     * Si les valeurs autoris�es sont des chaines, la casse des caract�res et
     * les �ventuels espaces de d�but et de fin sont ignor�s.
     *
     * A l'issue du test, la valeur du param�tre en cours de validation est
     * toujours strictement identique � l'une des valeurs de conversion.
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $request->convert('author', array('azimov'=>'IA', 'bradbury'=>'RB'))->ok();
     * </code>
     *
     * @param string|null $key
     * @param array|null $values
     * @return Request $this pour permettre le chainage des appels de m�thodes
     *
     * @throws RequestParameterBadValue si le test �choue
     */
    public function convert($key, array $values=null)
    {
        if (is_null($values))
        {
            $values=$key;
            $key=null;
        }
        $this->check($key);

        foreach((array)$this->_check as $i=>$value)
        {
            foreach($values as $src=>$dst)
            {
                if ($src===$value || (is_string($src) && strtolower(trim($src))===strtolower(trim($value))))
                {
                    if (is_array($this->_check))
                    {
                        $this->_check[$i]=$dst;
                    }
                    else
                    {
                        $this->_check=$dst;
                    }

                    continue 2;
                }
            }
            throw new RequestParameterBadValue($this->_checkName, $value);
        }
        return $this;
    }

    /**
     * Validation : v�rifie qu'un param�tre n'a qu'une seule valeur (ie n'est pas
     * un tableau)
     *
     * Si le param�tre est un scalaire, le test r�ussit. Si le param�tre est un
     * tableau ne contenant qu'un seul �l�ment, celui-ci est transform� en
     * scalaire. Dans tous les autres cas, le test �choue.
     *
     * Exemples d'utilisation :
     * <code>
     * $request->unique('nb')->ok();
     * $request->required('ref')->unique()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Request $this pour permettre le chainage des appels de m�thodes
     *
     * @throws RequestParameterUniqueValueExpected si le param�tre est un tableau
     * contenant plusieurs �l�ments
     */
    public function unique($key=null)
    {
        $value=$this->check($key);
        if (is_scalar($value))
        {
            return $this;
        }

        if (is_array($value))
        {
            if (count($value)===0)
            {
                $this->_check=null;
                return $this;
            }
            if (count($value)===1)
            {
                $this->_check=array_shift($this->_check);
                return $this;
            }
            throw new RequestParameterUniqueValueExpected($this->_checkName, $this->_check);
        }

        return $this;
    }

    /**
     * Validation : transforme un param�tre en tableau de valeurs
     *
     * Le param�tre en cours est transform� en tableau si ce n'en est pas
     * d�j� un.
     *
     * A l'issue du test, la valeur du param�tre en cours de validation est
     * toujours un tableau.
     *
     * Exemples d'utilisation :
     * <code>
     * $request->asArray('refs')->ok();
     * $request->required('refs')->asArray()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Request $this pour permettre le chainage des appels de m�thodes
     */
    public function asArray($key=null)
    {
        $this->_check=(array)$this->check($key);
        return $this;
    }


    /**
     * Validation : transforme un param�tre en tableau et v�rifie le nombre
     * d'�l�ments qu'il poss�de.
     *
     * Si $min et $max ont �t� indiqu�s, le test r�ussit si le nombre d'�l�ments
     * du tableau est compris entre min et max (bornes incluses).
     *
     * Si seul $min est indiqu�, le test r�ussit si le tableau a exactement $min
     * �l�ments.
     *
     * A l'issue du test, la valeur du param�tre en cours de validation est
     * toujours un tableau.
     *
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $request->count('refs',2)->ok(); // ok si exactement 2 �l�ments
     * $request->required('refs')->count(2,3)->ok(); // ok si 2 ou 3 �l�ments
     * </code>
     *
     * @param string|null $key
     * @param int $min
     * @param int $max
     *
     * @return Request $this pour permettre le chainage des appels de m�thodes
     *
     * @throws RequestParameterCountException si le test �choue
     */
    public function count($key, $min=null, $max=null)
    {
        switch(func_num_args())
        {
            case 1: // count(2)
                $min=$key;
                $key=null;
                break;
            case 2: // count(2, 3) ou count('t', 2)
                if (is_int($key))
                {
                    $min=$key;
                    $key=null;
                }
            //case 3: // count('t', 2, 3)
        }
        $this->_check=(array)$this->check($key);
        if (is_null($max))
        {
            if (count($this->_check) !== $min)
            {
                throw new RequestParameterCountException($this->_checkName, $this->_check, $min);
            }
        }
        else
        {
            if (count($this->_check) < $min || count($this->_check) > $max)
            {
                throw new RequestParameterCountException($this->_checkName, $this->_check, $min, $max);
            }
        }
        return $this;
    }


    /**
     * Validation : termine la validation d'un param�tre et retourne la valeur
     * finale du param�tre.
     *
     * Exemple d'utilisation :
     *
     * <code>
     * $request->set('flag','on')->bool()->ok(); // returns true
     * </code>
     *
     * @param string|null $key
     * @return mixed
     */
    public function ok($key=null)
    {
        $this->_checkName=null;
        return $this->_check;
    }

    /**
     * Retourne la requ�te en cours sous la forme d'une url indiquant le module,
     * l'action et les param�tres actuels de la requ�te
     *
     * @return string
     */
    public function getUrl()
    {
        return
            '/' . $this->_module . '/' . $this->_action
            . Routing::buildQueryString($this->_parameters, true);
    }

    /**
     * Alias de {@link getUrl()} : retourne la requ�te en cours sous forme
     * d'url.
     *
     * __toString est une m�thode magique de php qui est appell�e lorsque PHP
     * a besoin de convertir un objet en chaine de caract�res.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getUrl();
    }
}
/*
class HttpRequest extends Request
{

}

class CliRequest extends Request
{
    public function __construct()
    {
        parent::__construct($_SERVER['argv']);
    }
}
*/
/**
 * Classe de base des exceptions g�n�r�es par les fonctions de validation
 * de param�tres de {@link Request}
 *
 * @package     fab
 * @subpackage  module
 */
class RequestParameterException extends Exception
{
    public function __construct($message)
    {
        parent::__construct('Requ�te incorrecte : '.$message);
    }
};


/**
 * Exception g�n�r�e par {@link Request::required()} lorsqu'un param�tre
 * est absent
 *
 * @package     fab
 * @subpackage  module
 */
class RequestParameterRequired extends RequestParameterException
{
    public function __construct($param)
    {
        parent::__construct(sprintf('param�tre %s requis', $param));
    }
};


/**
 * Exception g�n�r�e par {@link Request::oneof()} lorsqu'un param�tre
 * a une valeur autoris�e
 *
 * @package     fab
 * @subpackage  module
 */
class RequestParameterBadValue extends RequestParameterException
{
    public function __construct($param, $value, $message='valeur incorrecte')
    {
        parent::__construct(sprintf('%s=%s, %s', $param, Utils::varExport($value,true), $message));
    }
};


/**
 * Exception g�n�r�e par {@link Request::unique()} lorsqu'un param�tre
 * a plusieurs valeurs
 *
 * @package     fab
 * @subpackage  module
 */
class RequestParameterUniqueValueExpected extends RequestParameterBadValue
{
    public function __construct($param, $value)
    {
        parent::__construct($param, $value, 'valeur unique attendue');
    }
};


/**
 * Exception g�n�r�e par {@link Request::bool()} lorsqu'un param�tre
 * n'est pas un bool�en
 *
 * @package     fab
 * @subpackage  module
 */
class RequestParameterBoolExpected extends RequestParameterBadValue
{
    public function __construct($param, $value)
    {
        parent::__construct($param, $value, 'bool�en attendu');
    }
};


/**
 * Exception g�n�r�e par {@link Request::int()} lorsqu'un param�tre
 * n'est pas un entier
 *
 * @package     fab
 * @subpackage  module
 */
class RequestParameterIntExpected extends RequestParameterBadValue
{
    public function __construct($param, $value)
    {
        parent::__construct($param, $value, 'entier attendu');
    }
};


/**
 * Exception g�n�r�e par {@link Request::min()} lorsqu'un param�tre
 * est inf�rieur au minimum autoris�
 *
 * @package     fab
 * @subpackage  module
 */
class RequestParameterMinExpected extends RequestParameterBadValue
{
    public function __construct($param, $value, $min)
    {
        parent::__construct($param, $value, sprintf('minimum attendu : %s', $min));
    }
};


/**
 * Exception g�n�r�e par {@link Request::max()} lorsqu'un param�tre
 * d�passe le maximum autoris�
 *
 * @package     fab
 * @subpackage  module
 */
class RequestParameterMaxExpected extends RequestParameterBadValue
{
    public function __construct($param, $value, $max)
    {
        parent::__construct($param, $value, sprintf('maximum attendu : %s', $max));
    }
};


/**
 * Exception g�n�r�e par {@link Request::count()} lorsqu'un param�tre
 * n'a pas le nombre correct de valeurs attendues
 *
 * @package     fab
 * @subpackage  module
 */
class RequestParameterCountException extends RequestParameterBadValue
{
    public function __construct($param, $value, $min, $max=null)
    {
        if (is_null($max))
            parent::__construct($param, $value, sprintf('%s valeurs attendues', $min));
        else
            parent::__construct($param, $value, sprintf('de %s � %s valeurs attendues', $min, $max));
    }
};

?>