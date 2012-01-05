<?php
/**
 * @package     fab
 * @subpackage  template
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: TemplateEnvironment.php 600 2008-02-06 15:21:54Z daniel.menard.bdsp $
 */

/**
 * G�re l'environnement d'ex�cution d'un template, c'est � dire l'ensemble
 * des sources de donn�es (tableaux, objets, callback...) pass�es en param�tre
 * lors de l'ex�cution d'un template ainsi que les variables temporaires
 * introduites par le code du template.
 * 
 * Cette classe n'est utilis�e que pendant la compilation d'un template 
 * (cf {@link TemplateCompiler}).
 * 
 * @package     fab
 * @subpackage  template
 */
class TemplateEnvironment
{
    /**
     * @var array L'environnement d'ex�cution du template, c'est � dire l'ensemble
     * des donn�es, tableaux, objets et callbacks pass�s en param�tres pour instancier le
     * template.
     * 
     * Initialement, il s'agit d'une copie exacte de l'environnement pass� � Template::run.
     * 
     * Si le template cr�e des donn�es locales (par exemple les variables cr��es par 
     * l'attribut as d'une balise loop), lors de l'entr�e dans le bloc, elles seront 
     * ajout�es au d�but du tableau afin qu'elles soient prioritaires (cf {@link push()}).
     * 
     * Lorsqu'on sort du bloc qui a cr�� les variables, on d�pile simplement le premier 
     * �l�ment du tableau pour retrouver l'environnement initial (cf {@link pop()}).
     * 
     * @access private
     */
    private $env=array();
    
    /**
     * @var integer indique le nombre d'�l�ments de {@link $env} qui sont des variables
     * locales. Pour chaque �l�ment i de $env, si i&lt;localCount, alors i est une variable locale,
     * sinon, c'est une donn�es pass�e en param�tre � Template::Run
     * 
     * @access private 
     */
    private $localCount=0;
    

    /**
     * @var array Liaisons entre chacune des variables rencontr�es dans le template
     * et la source de donn�es correspondante.
     * Ce tableau est construit au cours de la compilation. Les bindings sont ensuite
     * g�n�r�s au d�but du template g�n�r�.
     * 
     * @access private
     */
    private $bindings=array();
    
    
    /**
     * Initialise un nouvel environnement contenant les sources de donn�es pr�sentes dans le 
     * tableau pass� en param�tre
     * 
     * @param array|null $env un tableau contenant les sources de donn�es du template
     * @return void 
     */
    public function __construct($env=null)
    {
        $this->env=$env;
    }


    /**
     * Empile de nouvelles variables locales dans l'environnement d'ex�cution du template.
     *
     * @param array $vars un tableau contenant les variables � ajouter � l'environnement.
     * Pour chaque �l�ment, la cl� indique le nom de la variable (sans le dollar initial)
     * sous sa forme visible parl'utilisateur, et la valeur indique le code php qui sera 
     * utilis� chaque fois que cette variable sera utilis�e dans le template (en g�n�ral
     * il s'agit du nom de la variable r�elle).
     */
    public function push($vars)
    {
        array_unshift($this->env, $vars); // empile au d�but
        ++$this->localCount;
    }


    /**
     * Restaure l'environnement d'ex�cution tel qu'il �tait avant le dernier appel
     * � {@link push()} en supprimant de l'environnement le dernier tableau de 
     * variables ajout�.
     * 
     * @return array l'�l�ment d�pil�
     */
    public function pop()
    {
        --$this->localCount;
        return array_shift($this->env);    // d�pile au d�but
    }


    /**
     * Recherche une variable dans l'environnement et retourne le code php correspondant.
     * 
     * Si la variable est trouv�e et qu'il ne s'agit pas d'une variable locale, une liaison
     * est �tablie entre la variable telle qu'indiqu�e dans le template et la source de donn�es
     * correspondante.
     * 
     * @param string $var la variable recherch�e
     * @return string|boolean le code php correspondant � la variable ou false si aucune source
     * de donn�es ne conna�t la variable recherch�e.
     */
    public function get($var)
    {
//        debug && Debug::log('%s', $var);

        // Parcours toutes les sources de donn�es
        foreach ($this->env as $i=>$data)
        {
            $j=$i-$this->localCount;
            // Cl� d'un tableau de donn�es
            if (is_array($data) && array_key_exists($var, $data)) 
            {
//                debug && Debug::log('C\'est une cl� du tableau de donn�es');
                
                // Si c'est une variable locale introduite par un bloc, pas de binding
                if ($i<$this->localCount) return $data[$var];

                // Sinon, c'est une variable utilisateur, cr�e une liaison
                return $this->addBinding($var, 'Template::$data['.$j.'][\''.$var.'\']');
            }

            // Objet
            if (is_object($data))
            {
                // Propri�t� d'un objet
                
                /*
                    property_exists teste s'il s'agit d'une propri�t� r�elle de l'objet, mais ne fonctionne pas si c'est une propri�t� magique
                    isset teste bien les deux, mais retourera false pour une propri�t� r�elle existante mais dont la valeur est "null"
                    du coup il faudrait faire les deux...
                    mais isset, pour les m�thodes magiques, ne fonctionnera que si l'utilisateur a �crit une m�thode __get dans son objet
                    au final, on fait simplement un appel � __get (si la m�thode existe), et on teste si on r�cup�re "null" ou pas
                 */
 
                if (property_exists($data,$var) || (is_callable(array($data,'__get'))&& !(is_null(call_user_func(array($data,'__get'),$var)))))
                {
//                    debug && Debug::log('C\'est une propri�t� de l\'objet %s', get_class($data));
                    return $this->addBinding($var, 'Template::$data['.$j.']->'.$var);
                }
                
                // Cl� d'un objet ArrayAccess
                if ($data instanceof ArrayAccess)
                {
                    try // tester avec isset
                    {
//                        debug && Debug::log('Tentative d\'acc�s � %s[\'%s\']', get_class($data), $var);
                        $value=$data[$var]; // essaie d'acc�der, pas d'erreur ?

                        $code=$this->addBinding(get_class($data), 'Template::$data['.$j.']');
                        return $code.'[\''.$var.'\']';
                        // pas de r�f�rence : see http://bugs.php.net/bug.php?id=34783
                        // It is impossible to have ArrayAccess deal with references
                    }
                    catch(Exception $e)
                    {
//                        debug && Debug::log('G�n�re une erreur %s', $e->getMessage());
                    }
                }
//                else
//                    debug && Debug::log('Ce n\'est pas une cl� de l\'objet %s', get_class($data));
            }

            // Fonction de callback
            if (is_callable($data))
            {
                Template::$isCompiling++;
                ob_start();
                $value=call_user_func($data, $var);
                ob_end_clean();
                Template::$isCompiling--;
                
                // Si la fonction retourne autre chose que "null", termin�
                if ( ! is_null($value) )
                {
                    // Simple fonction 
                    if (is_string($data))
                    {
                        $code=$this->addBinding($data, 'Template::$data['.$j.']');
                        return $code.'(\''.$var.'\')';
                    }

                    // M�thode statique ou dynamique d'un objet
                    else // is_array
                    {
                        $code=$this->addBinding($data[1], 'Template::$data['.$j.']');
                        return 'call_user_func(' . $code.', \''.$var.'\')';
                    } 
                }
            }
            
            //echo('Datasource incorrecte : <pre>'.print_r($data, true). '</pre>');
        }
        //echo('Aucune source ne connait <pre>'. $name.'</pre>');
        return false;
    }
    
    /**
     * nom de la variable dans le template => (nom de la variable compil�e, code de la liaison)
     */
    private function addBinding($var, $code)
    {
        $h=$var.' --- '.$code;
        if (isset($this->bindings[$h]))
        {
//            if ($this->bindings[$var][1] !== $code)
//                throw new Exception("Appels multiples � addBinding('$var') avec des liaisons diff�rentes"
//                . ' Valeur actuelle : ' . $this->bindings[$var][1] .', '
//                . ' Nouvelle valeur : ' . $code
//                
//                );
            return $this->bindings[$h][0];
        }
        $temp=$this->getTemp($var);
        $this->bindings[$h]=array($temp,$code);
        return $temp;
    }
    
    public function getBindings()
    {
        $h="\n    //Liste des variables de ce template\n" ;
        foreach ($this->bindings as $name=>$binding)
            $h.='    ' . $binding[0] . '=' . $binding[1] . "; // variable $name\n";
            
    	return $h;
    }
    
    private $tempVars=array();
    
    /**
     * Alloue une nouvelle variable temporaire
     * 
     * La variable temporaire reste allou�e temps que {@link freeTemp()} n'est pas 
     * appell�e pour cette variable. Si d'autres appels � getTemp sont faits avec 
     * le m�me nom, les variables retourn�es seront num�rot�es (tmp, tmp2, etc.) 
     * 
     * @param string $name le nom souhait� pour la variable temporaire � allouer
     * @return string
     */
    public function getTemp($name='tmp')
    {
        if ($name[0]==='$') $name=substr($name,1);
        if ($name[0]!=='_') $name='_'.$name;

        // On travaille en minu, sinon, si on est appell� avec le m�me pr�fixe mais avec des casses diff�rentes,
        // on g�n�re des variables distingues vis � vis de php, mais assez difficiles � distinguer (par exemple getField1 et getfield1)
        $lcname=strtolower($name);
        $h=$lcname;
        
        for($i=2; $i<=100; $i++)
        {
            if (!isset($this->tempVars[$h]))
            {
                $this->tempVars[$h]=true;
                return '$'.$name.($i===2?'':$i-1);
            }
        	$h=$lcname.$i;
            
        }
        throw new Exception("Erreur interne : plus de 100 variables temporaires pour $name");
    }
    
    /**
     * Lib�re une variable temporaire allou�e par {@link getTemp()}
     * 
     * @param string $name le nom de la variable temporaire � lib�rer
     */
    public function freeTemp($name)
    {
        if ($name[0]==='$') $name=substr($name,1);
        if ($name[0]!=='_') $name='_'.$name;
        $name=strtolower($name);
        
        if (!isset($this->tempVars[$name]))
            throw new Exception("La variable temporaire $name n'existe pas");
        unset($this->tempVars[$name]);
    }
    
}
?>