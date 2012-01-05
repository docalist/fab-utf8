<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Classe de base abstraite pour toutes les interfaces d'import.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 */
abstract class AbstractImport
{
    /**
     * Path du fichier à charger.
     *
     * @var string
     */
    protected $path = '';


    /**
     * Handle du fichier en cours.
     *
     * @var resource
     */
    protected $file = null;


    /**
     * Le dernier enregistrement lu par getRecord()
     *
     * @var array
     */
    protected $record = null;


    /**
     * Constructeur.
     *
     * Ouvre le fichier passé en paramètre.
     *
     * @param string $path path absolu du fichier à ouvrir.
     */
    public function __construct($path)
    {
        $this->open($path);
    }


    /**
     * Destructeur.
     *
     * Ferme le fichier en cours.
     */
    public function __destruct()
    {
        $this->close();
    }


    /**
     * Ouvre le fichier indiqué.
     *
     * @param string $path
     */
    protected function open($path)
    {
        // Vérifie que le fichier existe
        if (! file_exists($path))
            throw new Exception("$path : fichier non trouvé");

        // Stocke son path
        $this->path = $path;

        // Ouvre le fichier texte en lecture
        $this->file = fopen($path, 'rt');
    }


    /**
     * Ferme le fichier en cours
     */
    protected function close()
    {
        if ($this->file)
        {
            fclose($this->file);
            $this->file = null;
        }
    }


    /**
     * Lit le prochain enregistrement du fichier.
     *
     * @return false|array un tableau contenant l'enregistrement lu ou false si la fin de fichier
     * est atteinte.
     */
    abstract public function getRecord();


    // ---------------------------------------------------------------------------------------------
    // Méthodes utilitaires
    // ---------------------------------------------------------------------------------------------

    /**
     * Transfère le contenu d'un ou plusieurs champs dans d'autres.
     *
     * La méthode transfert permet de déplacer, de concaténer ou de dupliquer des champs.
     *
     * Exemples :
     * <code>
     * // Transfère TITFRAN Dans TitOrigA
     * transfert('TITFRAN', 'TitOrigA')
     *
     * // Transfère tous les champ mots-clés dans le champ MotsCles
     * transfert('MOTSCLE1,MOTSCLE2,MOTSCLE3,MOTSCLE4,PERIODE', 'MotsCles');
     *
     * // Recopie MotsCles dans NouvDesc
     * transfert('MotsCles', 'MotsCles,NouvDesc');
     *
     * // Ajoute NouvDesc à MotsCles
     * transfert('MotsCles,NouvDesc', 'MotsCles');
     * </code>
     *
     * @param string $from un ou plusieurs champs sources (séparés par une virgule).
     * @param string $to un ou plusieurs champs destination.
     */
    public function transfert($from, $to)
    {
        $t = array();
        foreach(explode(',', $from) as $field)
        {
            $field = trim($field);
            if (! isset($this->record[$field])) continue;
            $t = array_merge($t, (array)$this->record[$field]);
            unset($this->record[$field]);
        }

        foreach(explode(',', $to) as $field)
        {
            $field = trim($field);
            $this->record[$field] = $t;
        }

        return $this;
    }

//is('CodLang', 'Mémoire', true/false) // tokenize(article) "est égal à" tokenize(search)
//match('CodLang', '~^fre$~', true/false) // tokenize(article) preg_match regexp

    /**
     *
     * Recherche une ou plusieurs chaines de caractères dans un ou plusieurs champs de
     * l'enregistrement.
     *
     * @param string $fields le ou les champs (séparés par une virgule) dans lesquels rechercher.
     *
     * @param string|array $patterns la ou les chaines de caractères recherchées.
     *
     * @param boolean $tokenize indique si la chaine recherchée et le contenu des champs sont
     * tokenisés avant de faire la comparaison (true par défaut).
     *
     * @return boolean true si la chaine recherchée a été trouvée, false sinon.
     */
    public function is($fields, $patterns, $tokenize = true)
    {
        // Détermine les champs à modifier
        if (empty($fields) || $fields === '*')
            $fields = implode(',', array_keys($this->record));

        // Tokenize les patterns
        $patterns = (array) $patterns;
        if ($tokenize)
        {
            foreach($patterns as & $pattern)
                $pattern = Utils::tokenize($pattern);
            unset($pattern);
        }

        // Teste tous les champs
        foreach(explode(',', $fields) as $field)
        {
            $field = trim($field);
            if (! isset($this->record[$field])) continue;

            // Teste tous les articles
            foreach((array) $this->record[$field] as $value)
            {
                // Tokenize l'article
                if ($tokenize) $value = Utils::tokenize($value);

                // Teste tous les patterns
                foreach ($patterns as $pattern)
                    if ($value === $pattern) return true;
            }
        }

        // Non trouvé
        return false;
    }

    /**
     * Teste si l'un des articles présents dans l'un des champs indiquées correspond à l'un des
     * patterns passés en paramètre.
     *
     * chaine ou regexp ?
     *
     * exemple :
     * match('TypDoc', 'article', 'fascicule');
     *
     * @param unknown_type $pattern
     */
    public function match($fields, $pattern)
    {
        if ($this->match('TypDoc,TypDocB', 'Ouvrage|Chapitre'))

        if ($this->match('DatEdit', 'FRE|FRA'))
        {

        }
    }


    /**
     * Applique un callback à tous les articles d'un champ.
     *
     * La méthode transform() permet d'appliquer une fonction ou une méthode à tous les articles
     * présents dans un ou plusieurs des champs présents dans l'enregistrement.
     *
     * Exemples d'utilisation :
     * <code>
     * // Faire un trim sur tous les champs et sur tous les articles
     * transform('trim');
     *
     * // Transformer des dates en format "aaa-mm-jj" en format Bdsp
     * transform('strtr', 'DatEdit,DatOrig', '-', '/'); // 2011-02-02 -> 2011/02/02
     *
     * // Supprimer la mention "pp." qui figure au début d'une pagination
     * transform('pregReplace', 'PageColl', '~p+\.?\s*(\d+)-(\d+)~', '$1-$2')
     * </code>
     *
     * @param string $callback le nom du callback à appeller pour chaque article de chacun des
     * champs indiqués dans $field. Il peut s'agir d'une méthode de la classe en cours ou d'une
     * fonction globale.
     *
     * Le callback recevra en paramètres l'article à transformer et les éventuels arguments
     * supplémentaires passés à transform(). Il doit retourner l'article modifié.
     *
     * Le callback doit avoir la signature suivante :
     * protected function callback(string $value) returns string
     *
     * ou, si vous utilisez les arguments optionnels :
     * protected function callback(string $value, $arg1, ...) returns string
     *
     * Le callback n'est pas appellé pour les champs vides.
     *
     * @param string $fields le ou les champs (séparés par une virgule) pour lesquels le callback
     * va être appellé. Si $fields est vide ou contient la chaine "*", le callback sera appellé
     * pour tous les champs de l'enregistrement.
     *
     * @param mixed $args... optionnel, des argument supplémentaires à passer au callback.
     *
     * @return $this
     */
    protected function transform($callback, $fields = null, $args=null)
    {
        // Détermine les champs à modifier
        if (empty($fields) || $fields === '*')
            $fields = implode(',', array_keys($this->record));

        // Détermine si le callback est une méthode de la classe ou une fonction globale
        if (is_string($callback) && method_exists($this, $callback))
            $callback = array($this, $callback);

        if (! is_callable($callback))
            throw new Exception('Callback non trouvé : ' . var_export($callback));

        // Détermine les arguments à passer au callback
        $args = func_get_args();
        $args = array_slice($args, 1);

        // Transforme tous les champs
        foreach(explode(',', $fields) as $field)
        {
            $field = trim($field);
            if (! isset($this->record[$field])) continue;

            if (is_scalar($this->record[$field]))
            {
                $args[0] = $this->record[$field];
                $this->record[$field] = call_user_func_array($callback, $args);
            }

            else foreach($this->record[$field] as & $value)
            {
                $args[0] = $value;
                $value = call_user_func_array($callback, $args);
            }
        }
        return $this;
    }


    /**
     * Version de preg_replace() utilisable comme callback lors d'un appel à {@link transform()}.
     *
     * La méthode transform() appelle le callback en lui passant comme premier argument la valeur à
     * modifier mais la fonction preg_replace() de php attend en premier paramètre l'expression
     * régulière à utiliser, la valeur à modifier étant en seconde position.
     *
     * On se contente donc de changer l'ordre des paramètres fournis à preg_replace.
     *
     * @param string $subject
     * @param string $pattern
     * @param string $replacement
     * @param int $limit
     * @param int $count
     */
    protected function pregReplace($subject, $pattern, $replacement = '', $limit = -1, & $count=null)
    {
        return preg_replace($pattern, $replacement, $subject, $limit, $count);
    }


    /**
     * Version de str_replace() utilisable comme callback lors d'un appel à {@link transform()}.
     *
     * La méthode transform() appelle le callback en lui passant comme premier argument la valeur à
     * modifier, ce qui n'est pas l'ordre attendu par str_replace. On se contente donc de changer
     * l'ordre des paramètres.
     *
     * @param string $subject
     * @param string|array $search
     * @param string|array $replace
     * @param int $count
     */
    protected function strReplace($subject, $search, $replace = '', & $count=null)
    {
        return str_replace($search, $replace, $subject, $count);
    }


    /**
     * Ajoute un préfixe à tous les articles présents dans le ou les champs indiqués.
     *
     * Exemple :
     * <code>
     * prefix('Ident', 'AED-BDSP : ');
     * </code>
     *
     * @param string $fields le ou les champs (séparés par une virgule) dans lesquels le préfixe
     * sera ajouté. Si $fields est vide ou contient la chaine "*", le préfixe sera ajouté à tous
     * les champs de l'enregistrement.
     *
     * @param string $prefix le préfixe à ajouter.
     *
     * @return $this
     */
    protected function prefix($fields, $prefix)
    {
        return $this->transform('addPrefix', $fields, $prefix);
    }


    /**
     * Callback utilisé par {@link prefix()}.
     *
     * @param string $value la valeur du champ.
     * @param string $prefix le préfixe à ajouter.
     */
    private function addPrefix($value, $prefix)
    {
        return "$prefix$value";
    }

    /**
     * Ajoute un suffixe à tous les articles présents dans le ou les champs indiqués.
     *
     * Exemple :
     * <code>
     * suffix('ORGCOM1', '/ com.');
     * </code>
     *
     * @param string $fields le ou les champs (séparés par une virgule) dans lesquels le suffixe
     * sera ajouté. Si $fields est vide ou contient la chaine "*", le suffixe sera ajouté à tous
     * les champs de l'enregistrement.
     *
     * @param string $suffix le suffixe à ajouter.
     *
     * @return $this
     */
    protected function suffix($fields, $suffix)
    {
        return $this->transform('addSuffix', $fields, $suffix);
    }


    /**
     * Callback utilisé par {@link suffix()}.
     *
     * @param string $value la valeur du champ.
     * @param string $prefix le préfixe à ajouter.
     */
    private function addSuffix($value, $suffix)
    {
        return "$value$suffix";
    }


    /**
     * Vide (supprime) un ou plusieurs des champs de l'enregistrement.
     *
     * Exemple :
     * <code>
     * clear('AUTB,ORGCOM1');
     * </code>
     *
     * @param string $fields le ou les champs (séparés par une virgule) à vider. Si $fields est
     * vide ou contient la chaine "*", tous les champs de l'enregistrement seront supprimés.
     *
     * Vous pouvez passer un ou plusieurs arguments et chaque argument peut contenir un ou
     * plusieurs noms de champs séparés par une virgule.
     *
     * Exemple :
     * <code>
     * clear('TitOrigA', 'TitOrigM');
     * clear('TitOrigA, TitOrigM');
     * </code>
     *
     * @return $this
     */
    protected function clear($fields = null)
    {
        // Si aucun champ n'a été indiqué, vide tout l'enregistrement
        if (empty($fields) || $fields === '*')
        {
            $this->record = array();
            return $this;
        }

        $fields = func_get_args();
        array_shift($fields);

        // Vide les champs indiqués
        foreach($fields as $field)
        {
            foreach(explode(',', $field) as $field)
            {
                $field = trim($field);
                unset($this->record[$field]);
            }
        }

        return $this;
    }


    /**
     * Concatène tous les articles présent dans un champ avec le séparateur indiqué.
     *
     * @param string $field le champ dont les article seront concaténés.
     * @param string $sep le sparateur à utiliser.
     */
    protected function concatValues($field, $sep='')
    {
        if (isset($this->record[$field]))
            $this->record[$field] = implode($sep, (array) $this->record[$field]);

        return $this;
    }


    /**
     * Examine l'enregistrement passé en paramètre et retourne le contenu du premier des champs
     * indiqués qui n'est pas vide
     *
     * @param string $fields la liste des champs à étudier. Vous pouvez passer un ou plusieurs
     * arguments et chaque argument peut contenir un ou plusieurs noms de champs séparés par une
     * virgule.
     *
     * Exemple :
     * <code>
     * firstFilled('TitOrigA', 'TitOrigM');
     * firstFilled('TitOrigA, TitOrigM');
     * </code>
     *
     * @return string|false retourne le contenu du premier champ non vide ou false si aucun des
     * champs indiqués n'est renseigné.
     */
    protected function firstFilled($fields)
    {
        $fields = func_get_args();
        array_shift($fields);

        foreach($fields as $field)
        {
            foreach(explode(',', $field) as $field)
            {
                $field = trim($field);
                if (isset($this->record[$field]) && ! empty($this->record[$field]))
                    return $this->record[$field];
            }
        }
        return '';
    }
}