<?php
/**
 * @package     fab
 * @subpackage  core
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe repr�sentant une collection de param�tres constitu�s d'un nom et d'une
 * valeur associ�e.
 *
 * Certaines m�thodes retourne $this pour permettre de chainer les appels de
 * m�thodes :
 *
 * <code>
 * $parameters
 *     ->set('query', 'health')
 *     ->set('max', 10)
 *     ->set('format', 'html');
 * </code>
 *
 * Parameters propose �galement des m�thodes (chain�es) permettant de valider
 * ais�ment les param�tres :
 *
 * <code>
 * $parameters
 *     ->required('ref')
 *     ->int()
 *     ->unique()
 *     ->min(1);
 * </code>
 *
 *
 * @package     fab
 * @subpackage  core
 */
class Parameters
{
    /**
     * Les param�tres de la requ�te
     *
     * @var array
     */
    private $_parameters=array();

    /**
     * Nom du param�tre en cours de validation
     *
     * @var string
     */
    private $_checkName;

    /**
     * Valeur actuelle du param�tre en cours de validation
     *
     * @var mixed
     */
    private $_check;


    /**
     * Construit une nouvelle collection de param�tres.
     *
     * Des param�tres suppl�mentaires peuvent �tre ajout�s � la collection
     * en utilisant {@link set()} et {@link add()}
     *
     * @param array $parameters ... des tableaux contenant les param�tres
     * initiaux de la collection.
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
     * M�thode statique permettant de cr�er une nouvelle collection de
     * param�tres.
     *
     * Php ne permet pas de chainer des m�thodes apr�s un appel � new :
     * <code>$parameters=new Parameters()->set('max', 10);</code>
     * g�n�re une erreur.
     *
     * La m�thode statique create permet de contourner le probl�me en �crivant :
     * <code>$parameters=Parameters::create()->set('max', 10);</code>
     *
     * @param array $parameters ... des tableaux contenant les param�tres
     * initiaux de la collection.
     */
    public static function create(array $parameters=array())
    {
        $class=__CLASS__;
        $request=new $class();
        $args=func_get_args();
        foreach($args as $arg)
        {
            $request->addParameters($arg);
        }
        return $request;
    }


    /**
     * Clone la collection en cours.
     *
     * @return Parameters
     */
    public function copy()
    {
        return clone $this;
    }


    /**
     * Ajoute un tableau de param�tres � la collection.
     *
     * @param array $parameters
     * @return Parameters $this pour permettre le chainage des appels de m�thodes.
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
     * Retourne la valeur du param�tre indiqu� ou null si la collection ne
     * contient pas le param�tre demand�.
     *
     * __get est une m�thode magique de php qui permet d'acc�der aux param�tres
     * de la collection comme s'il s'agissait de propri�t�s de l'objet
     * Parameters (par exemple <code>$parameters->max</code>)
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
     * si le param�tre indiqu� ne figure pas dans la collection.
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
     * Modifie la valeur d'un param�tre.
     *
     * __set est une m�thode magique de php qui permet de modifier un
     * param�tre comme s'il s'agissait d'une propri�t� de l'objet Parameters
     * (par exemple <code>$parametrs->max = 10</code>)
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
     * Modifie la valeur d'un param�tre.
     *
     * Exemple : $parameters->set('item', 12)
     *
     * @param string $key
     * @param mixed $value
     * @return Request $this pour permettre le chainage des appels de m�thodes
     */
    public function set($key, $value)
    {
        $this->__set($key, $value);
        return $this;
    }


    /**
     * Supprime le param�tre indiqu�
     *
     * __unset est une m�thode magique de php qui permet de supprimer un
     * param�tre comme s'il s'agissait d'une propri�t� de l'objet Parameters
     * (par exemple <code>unset($parameters->max)</code>)
     *
     * @param string $key
     */
    public function __unset($key)
    {
        unset($this->_parameters[$key]);
    }


    /**
     * Supprime des param�tres de la collection.
     *
     * La m�thode <code>clear()</code> permet de supprimer :
     * - tous les param�tres qui figure dans la collection,
     * - un param�tre unique,
     * - une valeur pr�cise d'un param�tre.
     *
     * Exemples :
     * - <code>$parameters->clear('item')</code> // supprime le param�tre
     *   item de la requ�te ;
     * - <code>$parameters->clear('item', 'article')</code> // supprime la
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
     * @return Parameters $this pour permettre le chainage des appels de m�thodes.
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
     * Supprime tous les param�tres sauf ceux dont le nom est indiqu� en
     * param�tre.
     *
     * Exemple :
     * <code>
     * $parameters->keepOnly('max', 'format'); // supprime tous sauf max et format
     * </code>
     *
     * @param string $arg nom du premier param�tre � conserver. Vous pouvez
     * indiquer autant d'argument arg que n�cessaire
     * @return Parameters $this pour permettre le chainage des appels de m�thodes.
     */
    public function keepOnly($arg)
    {
        $args=func_get_args();
        $this->_parameters=array_intersect_key($this->_parameters, array_flip($args));
        return $this;
    }


    /**
     * Supprime tous les param�tres vides.
     *
     * La m�thode <code>clearNull()</code> supprime de la collection tous les
     * param�tres dont la valeur est une chaine vide, un tableau vide ou la valeur null.
     *
     * @return Parameters $this pour permettre le chainage des appels de m�thodes.
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
     * Indique si la collection contient des param�tres.
     *
     * @return bool
     */
    public function hasParameters()
    {
        return count($this->_parameters)!==0;
    }


    /**
     * Retourne tous les param�tres pr�sents dans la collection.
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->_parameters;
    }


    /**
     * D�termine si le param�tre indiqu� existe.
     *
     * __isset() est une m�thode magique de php qui permet de tester l'existence
     * d'un param�tre comme s'il s'agissait d'une propri�t� de l'objet Parameters.
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
     * D�termine si le param�tre indiqu� existe.
     *
     * La fonction retourne true m�me si le param�tre � la valeur null
     *
     * @param string $key le nom du param�tre � tester.
     * @param mixed $value optionnel, la valeur � tester. Lorsque $value
     * est indiqu�e, la m�thode retourne true si le param�tre $key est d�finit
     * et s'il contient la valeur $value.
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
     * Ajoute une valeur au param�tre indiqu�.
     *
     * Add ajoute le param�tre indiqu� � la liste des param�tres de la requ�te.
     * Si le param�tre indiqu� existait d�j�, la valeur existante est transform�e
     * en tableau et la valeur indiqu�e est ajout�e au tableau obtenu.
     *
     * Pour remplacer compl�tement la valeur d'un param�tre existant, utiliser
     * la m�thode {@link set()}.
     *
     * @param string $key
     * @param mixed $value
     * @return Request $this pour permettre le chainage des appels de m�thodes.
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
     * Validation : g�n�re une exception si le param�tre indiqu� n'existe pas.
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
     * $parameters->required('item')->ok();
     * $parametes->bool('item')->required()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Parameters $this pour permettre le chainage des appels de m�thodes
     * @throws ParametersParameterRequired si le test �choue
     */
    public function required($key=null)
    {
        $value=(array)$this->check($key);

        if (count($value)===0)
        {
            throw new ParameterParameterRequired($this->_checkName);
        }

        foreach((array)$value as $value)
        {
            if (is_null($value) || (is_string($value) && trim($value)===''))
            {
                throw new ParametersParameterRequired($this->_checkName);
            }
        }

        return $this;
    }

    /**
     * Validation : donne une valeur par d�faut � un param�tre si celui-ci n'est
     * pas d�j� d�finit.
     *
     * @param string|null $key
     * @param scalar $default
     *
     * @return Parameters $this pour permettre le chainage des appels de m�thodes.
     */
    public function defaults($key, $default=null)
    {
        if (is_null($default))
        {
            $default=$key;
            $key=null;
        }
        $value=$this->check($key);

        if (is_null($value))
        {
            $this->_check=$default;
        }

        return $this;
    }


    /**
     * Validation : g�n�re une exception si le param�tre indiqu� n'est pas un
     * bool�en.
     *
     * La m�thode <code>bool()</code> reconna�t les bool�ens mais aussi les
     * chaines <code>'true','on', '1' </code> et <code>'false','off','0'</code>
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
     * $parameters->bool('flag')->ok();
     * $parameters->required('flag')->bool()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Parameters $this pour permettre le chainage des appels de
     * m�thodes.
     *
     * @throws ParametersParameterBoolExpected si le test �choue
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
                    throw new ParametersParameterBoolExpected($this->_checkName, $value);
                }
            }
            else
            {
                throw new ParametersParameterBoolExpected($this->_checkName, $value);
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
     * Validation : g�n�re une exception si le param�tre indiqu� n'est pas un
     * entier.
     *
     * La m�thode <code>int()</code> reconna�t les entiers mais aussi les
     * chaines repr�sentant un entier (les espaces de d�but ou de fin �ventuels
     * sont ignor�s).
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
     * $parameters->int('nb')->ok();
     * $parameters->required('nb')->int()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Parameters $this pour permettre le chainage des appels de m�thodes
     *
     * @throws ParametersParameterIntExpected si le test �choue
     */
    public function int($key=null)
    {
        $this->check($key);

        foreach((array)$this->_check as $i=>$value)
        {
            if (is_int($value))
            {
            }
            elseif (is_string($value) && ctype_digit(trim($value)))
            {
                $value=(int)$value;
            }
            elseif (is_float($value) && (round($value,0)==$value) && ($value > -PHP_INT_MAX-1) && ($value < PHP_INT_MAX))
            {
                $value=(int)$value;
            }
            else
            {
                throw new ParametersParameterIntExpected($this->_checkName, $value);
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
    public function regexp($key, $regexp)
    {
        return $this;

    }
    */


    /**
     * Validation : G�n�re une exception si le param�tre indiqu� est inf�rieur
     * au minimum autoris�.
     *
     * Si la valeur du param�tre est un tableau, le test est appliqu� � chacun
     * des �l�ments du tableau.
     *
     * A l'issue du test, la valeur du param�tre en cours de validation est
     * toujours du m�me type que l'argument <code>$min</code> indiqu�.
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $parameters->min('nb',5)->ok();
     * $parameters->min('author','azimov')->ok();
     * $parameters->required('nb')->min(5)->ok();
     * </code>
     *
     * @param string|null $key
     * @param scalar $min
     * @return Parameters $this pour permettre le chainage des appels de
     * m�thodes.
     *
     * @throws ParametersParameterMinExpected si le test �choue
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
            throw new ParametersParameterMinExpected($this->_checkName, $value, $min);
        }

        return $this;
    }


    /**
     * Validation : g�n�re une exception si le param�tre indiqu� est sup�rieur
     * au maximum autoris�.
     *
     * Si la valeur du param�tre est un tableau, le test est appliqu� � chacun
     * des �l�ments du tableau.
     *
     * A l'issue du test, la valeur du param�tre en cours de validation est
     * toujours du m�me type que l'argument <code>$max</code> indiqu�.
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $parameters->max('nb',20)->ok();
     * $parameters->max('author','bradbury')->ok();
     * $parameters->required('nb')->max(20)->ok();
     * </code>
     *
     * @param string|null $key
     * @param scalar $max
     *
     * @return Parameters $this pour permettre le chainage des appels de m�thodes
     *
     * @throws ParamatersParameterMaxExpected si le test �choue
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

            throw new ParametersParameterMaxExpected($this->_checkName, $value, $max);
        }
        return $this;
    }


    /**
     * Validation : g�n�re une exception si le param�tre indiqu� contient une
     * valeur non autoris�e.
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
     * Exemples d'utilisation :
     *
     * <code>
     * $parameters->oneof('nb',2,4,6)->ok();
     * $parameters->oneof('author', 'azimov', 'bradbury')->ok();
     * $parameters->required('nb')->oneof(2,4,6)->ok();
     * </code>
     *
     * @param string|null $key
     * @return Parameters $this pour permettre le chainage des appels de
     * m�thodes.
     *
     * @throws ParametersParameterBadValue si le test �choue.
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
            throw new ParametersParameterBadValue($this->_checkName, $value);
        }
        return $this;
    }


    /**
     * Validation : g�n�re une exception si le param�tre indiqu� est multivalu�.
     *
     * Si le param�tre est un scalaire, le test r�ussit. Si le param�tre est un
     * tableau ne contenant qu'un seul �l�ment, celui-ci est transform� en
     * scalaire. Dans tous les autres cas, le test �choue.
     *
     * Exemples d'utilisation :
     * <code>
     * $parameters->unique('nb')->ok();
     * $parameters->required('ref')->unique()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Parameters $this pour permettre le chainage des appels de
     * m�thodes.
     *
     * @throws ParametersParameterUniqueValueExpected si le param�tre est un
     * tableau contenant plusieurs �l�ments.
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
            throw new ParametersParameterUniqueValueExpected($this->_checkName, $this->_check);
        }

        return $this;
    }


    /**
     * Validation : transforme un param�tre en tableau de valeurs.
     *
     * Le param�tre en cours est transform� en tableau si ce n'en est pas
     * d�j� un.
     *
     * A l'issue du test, la valeur du param�tre en cours de validation est
     * toujours un tableau.
     *
     * Exemples d'utilisation :
     * <code>
     * $parameters->asArray('ref')->ok();
     * $parameters->required('ref')->asArray()->ok();
     * </code>
     *
     * @param string|null $key
     * @return Parameters $this pour permettre le chainage des appels de
     * m�thodes.
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
     * Si <code>$min</code> et <code>$max</code> ont �t� indiqu�s, le test
     * r�ussit si le nombre d'�l�ments du tableau est compris entre les deux
     *  (bornes incluses).
     *
     * Si seul <code>$min</code> est indiqu�, le test r�ussit si le tableau a
     * exactement <code>$min</code> �l�ments.
     *
     * A l'issue du test, la valeur du param�tre en cours de validation est
     * toujours un tableau.
     *
     * Exemples d'utilisation :
     *
     * <code>
     * $parameters->count('refs',2)->ok(); // ok si exactement 2 �l�ments
     * $parameters->required('refs')->count(2,3)->ok(); // ok si 2 ou 3 �l�ments
     * </code>
     *
     * @param string|null $key
     * @param int $min
     * @param int $max
     *
     * @return Parameters $this pour permettre le chainage des appels de
     * m�thodes.
     *
     * @throws ParametersParameterCountException si le test �choue
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
                throw new ParametersParameterCountException($this->_checkName, $this->_check, $min);
            }
        }
        else
        {
            if (count($this->_check) < $min || count($this->_check) > $max)
            {
                throw new ParametersParameterCountException($this->_checkName, $this->_check, $min, $max);
            }
        }
        return $this;
    }


    /**
     * Validation : termine la validation d'un param�tre et retourne sa valeur
     * finale.
     *
     * Exemple d'utilisation :
     *
     * <code>
     * $parameters->set('flag','on')->bool()->ok(); // returns true
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
     * Retourne les param�tres en cours sous la forme d'une query string.
     *
     * @return string
     */
    public function asQueryString()
    {
        return Routing::buildQueryString($this->_parameters, true);
    }

    /**
     * Alias de {@link asQueryString()} : retourne la requ�te en cours sous forme
     * d'url.
     *
     * __toString est une m�thode magique de php qui est appell�e lorsque PHP
     * a besoin de convertir un objet en chaine de caract�res.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->asQueryString();
    }
}

/**
 * Classe de base des exceptions g�n�r�es par les fonctions de validation
 * de param�tres de {@link Parameters}
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterException extends Exception
{
    public function __construct($message)
    {
        parent::__construct('Erreur : '.$message);
    }
};


/**
 * Exception g�n�r�e par {@link Parameters::required()} lorsqu'un param�tre
 * est absent
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterRequired extends ParametersParameterException
{
    public function __construct($param)
    {
        parent::__construct(sprintf('param�tre %s requis', $param));
    }
};


/**
 * Exception g�n�r�e par {@link Parameters::oneof()} lorsqu'un param�tre
 * contient une valeur non autoris�e.
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterBadValue extends ParametersParameterException
{
    public function __construct($param, $value, $message='valeur incorrecte')
    {
        parent::__construct(sprintf('%s=%s, %s', $param, Utils::varExport($value,true), $message));
    }
};


/**
 * Exception g�n�r�e par {@link Parameters::unique()} lorsqu'un param�tre
 * est multivalu�.
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterUniqueValueExpected extends ParametersParameterBadValue
{
    public function __construct($param, $value)
    {
        parent::__construct($param, $value, 'valeur unique attendue');
    }
};


/**
 * Exception g�n�r�e par {@link Parameters::bool()} lorsqu'un param�tre
 * n'est pas un bool�en
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterBoolExpected extends ParametersParameterBadValue
{
    public function __construct($param, $value)
    {
        parent::__construct($param, $value, 'bool�en attendu');
    }
};


/**
 * Exception g�n�r�e par {@link Parameters::int()} lorsqu'un param�tre
 * n'est pas un entier.
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterIntExpected extends ParametersParameterBadValue
{
    public function __construct($param, $value)
    {
        parent::__construct($param, $value, 'entier attendu');
    }
};


/**
 * Exception g�n�r�e par {@link Parameters::min()} lorsqu'un param�tre
 * est inf�rieur au minimum autoris�.
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterMinExpected extends ParametersParameterBadValue
{
    public function __construct($param, $value, $min)
    {
        parent::__construct($param, $value, sprintf('minimum attendu : %s', $min));
    }
};


/**
 * Exception g�n�r�e par {@link Parameters::max()} lorsqu'un param�tre
 * d�passe le maximum autoris�.
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterMaxExpected extends ParametersParameterBadValue
{
    public function __construct($param, $value, $max)
    {
        parent::__construct($param, $value, sprintf('maximum attendu : %s', $max));
    }
};


/**
 * Exception g�n�r�e par {@link Parameters::count()} lorsqu'un param�tre
 * n'a pas le nombre correct de valeurs attendues.
 *
 * @package     fab
 * @subpackage  core
 */
class ParametersParameterCountException extends ParametersParameterBadValue
{
    public function __construct($param, $value, $min, $max=null)
    {
        if (is_null($max))
            parent::__construct($param, $value, sprintf('%s valeurs attendues', $min));
        else
            parent::__construct($param, $value, sprintf('de %s � %s valeurs attendues', $min, $max));
    }
};