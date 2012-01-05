<?php
/**
 * @package     fab
 * @subpackage  database
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: XapianLookupHelpers.php 1231 2010-12-14 11:09:19Z daniel.menard.bdsp $
 */

/**
 * Classe de base pour les lookup sur une base Xapian.
 *
 * @package     fab
 * @subpackage  database
 */
abstract class LookupHelper
{
    /**
     * L'it�rateur Xapian de d�but tel que retourn� par
     * {@link XapianDatabase::allterms_begin()}.
     *
     * @var XapianTermIterator
     */
    protected $begin = null;


    /**
     * L'it�rateur Xapian de fin tel que retourn� par
     * {@link XapianDatabase::allterms_end()}.
     *
     * @var XapianTermIterator
     */
    protected $end = null;


    /**
     * Le nombre maximum de suggestions � retourner.
     *
     * @var int
     */
    protected $max = null;


    /**
     * Pr�fixe Xapian des termes � rechercher.
     *
     * @var string
     */
    protected $prefix = '';


    /**
     * Tableau contenant la version {@link Utils::tokenize() tokenis�e} des mots pour
     * lesquels on veut obtenir des suggestions.
     *
     * @var array
     */
    protected $words = null;


    /**
     * Le format (style {@link http://php.net/sprintf sprintf}) � utiliser pour mettre les mots de
     * l'utilisateur en surbrillance dans les suggestions obtenues.
     *
     * @var string
     */
    protected $format = '';


    /**
     * Indique s'il faut ou non trier la liste de r�sultats obtenus.
     *
     * Pour {@link TermLookup} et {@link ValueLookup}, c'est inutile de trier : comme on parcourt
     * l'index de fa�on ascendante, les r�sultats sont d�j� tri�s par ordre alphab�tique sur les
     * versions tokenis�es des suggestions trouv�es.
     *
     * Les modules descendants ({@link SimpleTableLookup}) peuvent surcharger cette propri�t�
     * pour indiquer qu'un tri des r�sultats est requis.
     *
     * @var bool
     */
    protected $alreadySorted = true;


    /**
     * Active ou d�sactive les messages de d�bogage.
     *
     * @var bool
     */
    protected $debug=false;


    /**
     * Constructeur. Active le d�bogage si l'option 'debug' figure dans la query string
     * de la requ�te en cours.
     */
    public function __construct()
    {
        $this->debug = isset($_GET['debug']) && $_GET['debug'] !== 0;
    }


    /**
     * D�finit les {@link XapianTermIterator it�rateurs Xapian} � utiliser.
     *
     * @param XapianTermIterator $begin it�rateur de d�but.
     * @param XapianTermIterator $end it�rateur de fin.
     */
    public function setIterators(XapianTermIterator $begin, XapianTermIterator $end)
    {
        $this->begin = $begin;
        $this->end = $end;
    }


    /**
     * D�finit le pr�fixe des termes � utiliser pour la recherche des
     * suggestions.
     *
     * @param string $prefix
     */
    public function setPrefix($prefix='')
    {
        $this->prefix = $prefix;
    }


    /**
     * Trie le tableau de suggestions par ordre alphab�tique.
     *
     * @param array $result le tableau de suggestions � trier.
     * @param array $sortKeys le tableau contenant les cl�s de tri.
     */
    protected function sort(array & $result, array & $sortKeys)
    {
        array_multisort($sortKeys, array_keys($result), $result);
    }


    /**
     * Recherche des suggestions pour les termes pass�s en param�tre.
     *
     * @param string $value la chaine de recherche pour laquelle on souhaite obtenir
     * des suggestions.
     *
     * @param int $max le nombre maximum de suggestions � retourner
     * (valeur par d�faut : 10, 0 : pas de limite).
     *
     * @param string $format Le format (style {@link http://php.net/sprintf sprintf}) �
     * utiliser pour mettre les mots de l'utilisateur en surbrillance dans les
     * suggestions obtenues.
     *
     * @return array un tableau contenant les suggestions obtenues. Chaque cl�
     * du tableau contient une suggestion et la valeur associ�e contient le
     * nombre total d'occurences de cette entr�e dans la base.
     *
     * Le tableau est tri� par ordre alphab�tique sur la version tokenis�e des entr�es.
     *
     * Exemple :
     * <code>
     * array
     * (
     *     'droit du malade' => 10,
     *     'information du malade' => 3
     * )
     * </code>
     */
    public function lookup($value, $max=10, $format='<strong>%s</strong>')
    {
        // Stocke le format utilis� pour la surbrillance
        $this->format = empty($format) ? null : $format;

        // Stocke le nombre de suggestions � retourner
        $this->max = ($max<=0) ? PHP_INT_MAX : $max;

        // Tokenize l'expression recherch�e
        $this->words = Utils::tokenize($value);
        if (empty($this->words)) $this->words[] = ' ';

        // Recherche pour chaque mot les entr�es correspondantes
        if ($this->debug)
            echo get_class($this), "::getEntries(), value=<code>$value</code>, max=<code>$max</code>, format=<code>$format</code><br />";

        $result = $sortKeys = array();
        $this->getEntries($result, $sortKeys);

        // Trie les r�sultats si c'est n�cessaire
        if (! $this->alreadySorted)
            $this->sort($result, $sortKeys);

        if ($this->debug)
        {
            echo "<div style='float:left'>Cl�s de tri : <pre>", var_export($sortKeys,true), "</pre></div>";
            echo "<div style='float:left'>R�sultats : <pre>", var_export($result,true), "</pre></div>";
            echo '<hr style="clear: both" />';
        }

        // Limite � $max r�ponses
        if (count($result) > $this->max)
            $result=array_slice($result, 0, $this->max);

        // Retourne le tableau obtenu
        return $result;
    }


    /**
     * Recherche les entr�es de la table  qui commencent par l'expression indiqu�e par
     * l'utilisateur.
     *
     * @param array $result les entr�es obtenues, sous la forme d'un tableau
     * <code>"entr�e riche" => nombre total d'occurences dans la base</code>.
     *
     * @param array $sortKeys un tableau, synchronis� avec <code>$result</code>, contenant
     * les versions tokenis�es des entr�es trouv�es. Ce tableau est utilis� pour
     * {@link sort() trier} les r�sultats obtenus par ordre alphab�tique.
     */
    abstract protected function getEntries(array & $result, array & $sortKeys);


    /**
     * Met en surbrillance (selon le format en cours) les termes de recherche
     * de l'utilisateur dans l'entr�e pass�e en param�tre.
     *
     * @param string $entry l'entr�e � surligner.
     * @param int $length le nombre de caract�res � mettre en surbrillance.
     * @return string la chaine <code>$entry</code> dans laquelle la sous-chaine de
     * 0 � <code>$length</code> est mise en surbrillance.
     */
    protected function highlight($entry, $length)
    {
        if (is_null($this->format)) return $entry;
        return sprintf($this->format, substr($entry, 0, $length)) . substr($entry, $length);
    }
}


/**
 * LookupHelper permettant de rechercher des suggestions parmi les termes
 * simples pr�sent dans l'index Xapian.
 *
 * Contrairement aux autres, ce helper ne sait pas faire des suggestions pour
 * une chaine contenant plusieurs mots (la raison est simple : les termes dans
 * l'index sont des mots uniques).
 *
 * Si une expression contenant plusieurs mots est recherch�e, seul le dernier
 * mot sera utilis� pour faire des suggestions.
 *
 * @package     fab
 * @subpackage  database
 */
class TermLookup extends LookupHelper
{
    /**
     * Retourne le terme � partir duquel va d�marrer la recherche dans l'index.
     *
     * Pour un lookup de type {@link TermLookup}, il s'agit de la version tokenis�e du
     * dernier mot figurant dans l'expression de recherche indiqu�e par l'utilisateur.
     *
     * @return string
     */
    protected function getSearchTerm()
    {
        return end($this->words);
    }


    /**
     * Formatte une entr�e telle qu'elle doit �tre pr�sent�e � l'utilisateur.
     *
     * La m�thode supprime le pr�fixe de l'entr�e,
     *
     * @param string $entry l'entr�e � formatter.
     * @return string l'entr�e mise en forme.
     */
    protected function formatEntry($entry)
    {
        return substr($entry, strlen($this->prefix));
    }


    /**
     * @inheritdoc
     */
    protected function getEntries(array & $result, array & $sortKeys)
    {
        // D�termine l'entr�e de d�but et la chaine recherch�e
        $search = $this->getSearchTerm();
        $start = $this->prefix . $search;
        if ($this->debug) echo "search = $search, start=$start<br />";

        // Va au d�but de la liste
        if ($this->debug) echo "<b style='color:red'>skip_to('$start')</b><br />";
        $this->begin->skip_to($start);

        // Boucle tant que les entr�es commencent par ce qu'on cherche
        while (! $this->begin->equals($this->end))
        {
            // R�cup�re l'entr�e en cours
            $key = $this->begin->get_term();
            if ($this->debug) echo "<li><b style='color:green'>$key</b><br /></li>";

            // Si elle ne commence pas par la chaine recherch�e, termin�
            if (strncmp($key, $start, strlen($start)) !== 0) break;

            // Formatte l'entr�e telle qu'elle doit �tre pr�sent�e � l'utilisateur
            $key = $this->formatEntry($key);
            $entry = $this->highlight($key, strlen($search));

            // Stocke la suggestion obtenue dans les tableaux r�sultat
            if (isset($result[$entry]))
            {
                $result[$entry] = max($result[$entry], $this->begin->get_termfreq());
            }
            else
            {
                $result[$entry] = $this->begin->get_termfreq();
                $sortKeys[] = $key;
            }

            // Si on a trouv� assez de suggestions, termin�
            if (count($result) >= $this->max) break;

            // Passe � l'entr�e suivante dans l'index
            $this->begin->next();
        }
        if ($this->debug) echo "done<br />";
    }
}

/**
 * LookupHelper permettant de rechercher des suggestions parmi les articles
 * pr�sents dans l'index Xapian.
 *
 * Ce helper recherche les articles qui commencent par l'un des mots indiqu�s
 * par l'utilisateur et ne retient que ceux contenant tous les mots (ou d�but
 * de mot) figurant dans l'expression recherch�e.
 *
 * @package     fab
 * @subpackage  database
 */
class ValueLookup extends TermLookup
{
    /*
     * Les r�sultats issus d'un index "Article" sont d�j� correctement tri�s par ordre alphab�tique.
     * Donc, c'est inutile de retrier les r�sultats.
     *
     * Nanmoins, j'ai trouv� un cas ou cette propri�t� n'est pas respect�e : sur la table PasEng,
     * en recherchant la valeur "a", on obtient les entr�es
     * 28:_a3_chromosome_
     * 28:_a_
     *
     * Comme on utilise un underscore � la fin des articles et que, selon l'ordre ascii, le chiffre
     * "3" (code = 51) est plus petit que l'underscore (code = 95), le tri obtenu n'est pas correct.
     *
     * Pour que l'erreur se produise, il faut qu'on ait � la fois, dans une table des articles,
     * un article et le m�me article suivi d'un chiffre (le underscore sera avant n'importe quelle
     * lettre).
     *
     * A priori, ce cas est super rare, donc j'ai consid�r� que ce n'�tait pas la peine de trier
     * � chaque fois pour si peu.
     *
     * Dans le cas contraire, activer la ligne ci-dessous.
     */
    //protected $alreadySorted = false;



    /**
     * Retourne le terme � partir duquel va d�marrer la recherche dans l'index.
     *
     * Pour un lookup de type {@link ValueLookup}, il s'agit d'une chaine de la forme
     * <code>_mot1_mot2_mot3</code> construite � partir de la version tokenis�e des mots qui
     * figurent dans l'expression de recherche indiqu�e par l'utilisateur.
     *
     * @return string
     */
    protected function getSearchTerm()
    {
        return '_' . implode('_', $this->words);
    }


    /**
     * Formatte une entr�e telle qu'elle doit �tre pr�sent�e � l'utilisateur.
     *
     * En plus des traitements appliqu�s par la {@link TermLookup::formatEntry() m�thode parente},
     * la m�thode supprime les caract�res "_" pr�sents dans l'article.
     *
     * @param string $entry l'entr�e � formatter.
     * @return string l'entr�e mise en forme.
     */
    protected function formatEntry($entry)
    {
        return trim(strtr(parent::formatEntry($entry), '_', ' '));
    }


    /**
     * @inheritdoc
     */
    protected function highlight($entry, $length)
    {
        // Comme les articles commencent par un '_' initial qu'on a supprim� dans formatEntry(),
        // il faut retrancher 1 � $length.
        return parent::highlight($entry, $length-1);
    }
}


/**
 * LookupHelper permettant de rechercher des suggestions au sein d'une table de
 * lookup simple.
 *
 * Ce helper recherche la forme riche des entr�es qui commencent par l'un des
 * mots indiqu�s par l'utilisateur et ne retient que celles qui commencent par l'expression
 * de recherche indiqu�e par l'utilisateur.
 *
 * @package     fab
 * @subpackage  database
 */
class SimpleTableLookup extends LookupHelper
{
    /**
     * $charFroms et $charTo repr�sentent les tables de conversion de caract�res utilis�es pour
     * rechercher les entr�es au format riche � partir de la chaine de recherche indiqu�e par
     * l'utilisateur.
     *
     * Remarque : les chaines sont "tri�es" de telle fa�on que les it�rateurs utilis�s
     * par {@link SimpleTableLookup} ne font que avancer (utiliser la m�thode sortByChar()
     * qui figure en commentaires � la fin de cette classe).
     *
     * Par rapport aux chaines d�finies dans Utils::Tokenize(), j'ai ajout� tous les signes de
     * ponctuation "tapables au clavier" pour permettre de trouver des entr�es telles que
     * "(LA) REVUE DU PRATICIEN".
     */
//  protected static $charFroms = '!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~Aa������������BbCc��Dd��Ee��������FfGgHhIi��������JjKkLlMmNn��Oo����������PpQqRrSs�Tt��Uu��������VvWwXxYy���Zz�����';
//  protected static $charTo    =   '                                aaaaaaaaaaaaaabbccccddddeeeeeeeeeeffgghhiiiiiiiiiijjkkllmmnnnnooooooooooooppqqrrsssttttuuuuuuuuuuvvwwxxyyyyyzz�����';

// Version simplifi�e avec des lettres en moins
    protected static $charFroms = ' !"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~0123456789Aa������������BbCc��Dd��Ee��������FfGgHhIi���JjKkLlMmNn�Oo��PpQqRrSsTt��Uu��������VvWwXxYy���Zz�����';

    /**
     * cf {@link $charFroms}.
     *
     * @var string
     */
    protected static $charTo    =   '                                 0123456789aaaaaaaaaaaaaabbccccddddeeeeeeeeeeffgghhiiiiijjkkllmmnnnooooppqqrrssttttuuuuuuuuuuvvwwxxyyyyyzz�����';


    /**
     * @inheritdoc
     */
    protected $alreadySorted = false;


    /**
     * Stocke l'entr�e d'index en cours.
     *
     * @var string
     */
    private $entry='';


    /**
     * @inheritdoc
     */
    protected function getEntries(array & $result, array & $sortKeys)
    {
        // $this->sortByChar(); die(); // D�sactiver cette ligne pour trier charFroms et charTo correctement.

        if ($this->debug) echo '<pre>';

        // Construit la version tokeniz�e de la chaine recherch�e
        // Entre chaque mot, on ins�re un caract�re "�" qui repr�sente un espace de taille variable
        $this->search = implode('�', $this->words);

        // Apparemment, une fois qu'un TermIterator a atteint la fin de la liste, tous les appels
        // ult�rieurs � skip_to() "�chouent" (i.e. on reste sur EOF).
        // Pour �viter cela, on clone le TermIterator utilis�.
        // Code de test :
        // $this->begin->skip_to('T5:~'); // eof===true (i.e. $this->begin->equals($this->end)
        // $this->begin->skip_to('T5:LA'); // eof encore � true, alors que cela ne devrait pas

        // Sauvegarde l'it�rateur de d�but
        $begin = $this->begin;

        // Clone l'it�rateur et recherche les entr�es qui commencent par ce que l'utilisateur a tap�
        if ($this->debug) echo "<h1>this.search=$this->search</h1>";
        $this->begin = new XapianTermIterator($begin);
        $this->search($this->search, $result, $sortKeys);

        // Utilise l'it�rateur initial et recherche les entr�es qui commencent par un blanc
        // exemple : "(LA) REVUE DU PRATICIEN"
        if ($this->search !== ' ') // si value=='(', words=array(' '), this.search==' ' et c'est inutile de relancer la recherche
        {
            $this->begin = $begin;
            $this->search = '�' . $this->search;
            $this->entry = ''; // R�initialise l'index pour que la recherche recommence au d�but
            if ($this->debug) echo "<h1>this.search=$this->search</h1>";
            $this->search($this->search, $result, $sortKeys);
        }
    }


    /**
     * G�n�re toutes les variations de lettres possibles � l'offset indiqu� et recherche les
     * entr�es correspondantes.
     *
     * @param string $word
     * @param array $result
     * @param array $sortKeys
     * @param int $offset
     */
    protected function search($word, array & $result, array & $sortKeys, $offset=0)
    {
        // Appelle la m�thode space() si c'est un "blanc" � g�om�trie variable
        if ($word[$offset] === '�')
            $this->space($word, $result, $sortKeys, $offset);

        // Appelle la m�thode letter() sinon
        else
            $this->letter($word, $result, $sortKeys, $offset);
    }


    /**
     * Traite les espaces trouv�s dans la chaine de recherche tokenis�e.
     *
     * G�n�re toutes les variations possibles � l'offset indiqu� et recherche les entr�es
     * correspondantes.
     *
     * @param string $word
     * @param array $result
     * @param array $sortKeys
     * @param int $offset
     */
    protected function space($word, array & $result, array & $sortKeys, $offset)
    {
        // Dans la chaine tokenis�e, un espace repr�sente de un � 7 caract�res "blancs".
        // Dans les autcoll, par exemple, on : "(E.N.S.P.). .. Service" (7 blancs entre P et S)
        for ($repeat=1 ; $repeat <= 7 ; $repeat++)
        {
            $this->entry='';
            $h = substr($word, 0, $offset) . str_repeat(' ', $repeat) . substr($word, $offset+1);

            $this->variations($h, $result, $sortKeys, $offset);
        }

        // Suppression des espaces en trop
        // Exemple : l'utilisateur a tap� "BEH Web" et on a "BEHWeb" dans la table
        // On ne le fait pas si l'espace est au d�but ou � la fin (sinon, on sort une seconde fois
        // toutes les suggestions d�j� trouv�es)
        if ($offset > 0 && $offset < strlen($word)-1)
        {
            $this->entry='';
            $h = substr($word, 0, $offset) . substr($word, $offset+1);
            $this->variations($h, $result, $sortKeys, $offset);
        }
    }


    /**
     * Traite les lettres et les chiffres trouv�s dans la chaine de recherche tokenis�e.
     *
     * G�n�re toutes les variations possibles � l'offset indiqu� et recherche les entr�es
     * correspondantes.
     *
     * @param string $word
     * @param array $result
     * @param array $sortKeys
     * @param int $offset
     */
    protected function letter($word, array & $result, array & $sortKeys, $offset)
    {
        // Recherche toutes les variations possibles
        $this->variations($word, $result, $sortKeys, $offset);

        // Recherche de sigles
        // Exemple : l'utilisateur a tap� "SESI" et on a "S.E.S.I." dans la table
        if ($offset && ctype_alpha($word[$offset-1]))
        {
            // R�initialise l'index pour que la recherche recommence au d�but
            $this->entry = '';

            // Ins�re un espace (un '.') juste apr�s la lettre en cours
            $h = substr($word, 0, $offset) . ' ' . substr($word, $offset);

            // Teste s'il existe des entr�es commen�ant par le terme obtenu
            $this->variations($h, $result, $sortKeys, $offset);
        }
    }

    /**
     * G�n�re toutes les variations de caract�re possibles � l'offset indiqu� et stocke les
     * entr�es qui correspondent.
     *
     * @param string $word
     * @param array $result
     * @param array $sortKeys
     * @param int $offset
     */
    protected function variations($word, array & $result, array & $sortKeys, $offset)
    {
        // D�termine le caract�re pour lequel on va g�n�rer toutes les variantes
        $char = $word[$offset];
        $lastPos = strlen($word)-1;

        // G�n�re toutes les variantes possibles pour ce caract�re
        $i=0;
        while (false !== $i=strpos(self::$charTo, $char, $i))
        {
            // Modifie le mot avec la variante de la lettre en cours
            $word[$offset] = self::$charFroms[$i++];

            // Construit le pr�fixe utilis� pour la recherche
            $start = $this->prefix . substr($word, 0, $offset+1);

            // Passe � la variante suivante s'il n'y a aucune entr�e qui commence par $start
            if (! $this->getEntry($start)) continue;

            // Si on vient de traiter la derni�re lettre du mot , stocke les entr�es obtenues
            if ($offset === $lastPos)
            {
                if ($this->debug) echo "MATCH<br />";
                $this->getMatches($start, $result, $sortKeys);
            }

            // Sinon, r�cursive et g�n�re les variantes possibles � la position suivante
            else
            {
                // Passe � la lettre suivante
                $this->search($word, $result, $sortKeys, $offset+1);
            }
        }
    }

    /**
     * Stocke dans les tableaux r�sultats toutes les entr�es qui commencent par le pr�fixe indiqu�.
     *
     * @param string $start
     * @param array $result
     * @param array $sortKeys
     */
    protected function getMatches($start, array & $result, array & $sortKeys)
    {
        // debug, inutile
        if (! (strncmp($start, $this->entry, strlen($start)) === 0))
            if ($this->debug) echo "<li><b style='color:RED'>ERROR start = $start, entry=$this->entry</b><br /></li>";

        // Stocke toutes les entr�es qui commencent par start
        $nb = 0;
        while (strncmp($start, $this->entry, strlen($start)) === 0)
        {
            if ($this->debug) echo "<li><b style='color:green'>$this->entry</b><br /></li>";

            // Supprime le pr�fixe de l'entr�e (par exemple "T3:")
            $entry = substr($this->entry, strlen($this->prefix));

            // D�termine la cl� utilis�e pour trier l'entr�e
            $key = implode('', Utils::tokenize($entry)) ;

            // Met les termes en surbrillance
            $entry = $this->highlight($entry, strlen($start)-strlen($this->prefix));

            // Stocke la suggestion dans le tableau r�sultat
            if (isset($result[$entry]))
            {
                $result[$entry] = max($result[$entry], $this->begin->get_termfreq());
            }
            else
            {
                $result[$entry] = $this->begin->get_termfreq();
                $sortKeys[] = $key;
            }

            // Si on a trouv� suffisamment d'entr�es, termin�
            if (++$nb > $this->max) return;

            // Passe � l'entr�e suivante
            $this->begin->next();

            if ($this->begin->equals($this->end))
            {
                if ($this->debug) echo "EOF (1), begin===end<br />";
                return;
            }


            $this->entry=$this->begin->get_term();
        }

        if ($this->debug) echo "Done : ",substr($this->entry,0,strlen($start)+15),"...<br />";
    }


    /**
     * Positionne l'index sur la premi�re entr�e qui commence par le pr�fixe indiqu�.
     *
     * La propri�t� $this->entry est initialis�e avec l'entr�e trouv�e.
     *
     * @param false|string $start retourne false si aucune entr�e ne commence par $start ou
     * l'entr�e trouv�e sinon.
     *
     * @return bool <code>true</code> si une entr�e commen�ant par <code>$start</code> a �t�
     * trouv�e, <code>false</code> sinon.
     */
    protected function getEntry($start)
    {
        // Si l'entr�e en cours est plus grande que $start, inutile de faire un appel � skip_to()
        if (strncmp($start, $this->entry, strlen($start)) < 0) return false;

        // Positionne l'index sur les entr�es qui commencent par start
        if ($this->debug) echo "<b style='color:red'>skip_to('$start')</b><br />";
        $this->begin->skip_to($start);

        // R�cup�re l'entr�e obtenue
        if ($this->begin->equals($this->end)) return false;
        $this->entry=$this->begin->get_term();

        // Si elle ne commence pas par start, termin�
        if (strncmp($start, $this->entry, strlen($start)) !== 0) return false;

        // OK, trouv�
        return true;
    }

    /**
     * M�thode interne utilis�e pour trier les chaines charFroms et charTo
     * de telle mani�re que les it�rateurs Xapian ne fasse que "avancer" (ie
     * pas de retour en arri�re).
     *
     * Pour cela, on trie les caract�res d'abord par "charTo" puis par
     * "charFrom".
     */
    /* Cette fonction est volontairement en commentaires, l'activer en cas de besoin... */
/*
    private function sortByChar()
    {
        $charFroms = self::$charFroms;
        $charTo    = self::$charTo;

        // Cr�e un tableau de tableaux � partir des chaines
        $t=array();
        for ($i=0; $i<strlen($charTo); $i++)
        {
            $t[$charTo[$i]][]=$charFroms[$i];
        }

        // Tri des cl�s
        ksort($t, SORT_REGULAR);

        // Tri des valeurs
        foreach($t as &$chars)
            sort($chars, SORT_REGULAR);
        unset($chars);

        // Reconstitue les chaines
        $from='';
        $to='';
        foreach($t as $key=>$chars)
        {
            foreach($chars as $char)
            {
                $from.=$char;
                $to.=$key;
            }

        }

        header('Content-type: text/plain; charset=CP1252');

        // Affiche le r�sultat
        echo "Anciennes tables :\n";
        echo '    protected static $charFroms = ', var_export($charFroms,true), ";\n";
        echo '    protected static $charTo    =   ', var_export($charTo,true), ";\n";

        if ($charFroms===$from && $charTo===$to)
            echo "\nLes tables sont d�j� correctement tri�es.\n";
        else
        {
            echo "\nLes tables ne sont pas correctement tri�es :\n";
            echo "\nNouvelles tables :\n";
            echo '    protected static $charFroms = ', var_export($from,true), ";\n";
            echo '    protected static $charTo    =  ', var_export($to,true), ";\n";
        }
    }
*/
}

/**
 * LookupHelper permettant de rechercher des suggestions au sein des index
 * composant un alias.
 *
 * AliasLookup est en fait un aggr�gateur qui combine les suggestions retourn�es
 * par les LookupHelper qu'il contient.
 *
 * @package     fab
 * @subpackage  database
 */
class AliasLookup extends LookupHelper
{
    /**
     * @inheritdoc
     */
    protected $alreadySorted = false;


    /**
     * Liste des helpers qui ont �t� ajout�s via {@link add()}
     *
     * @var array
     */
    protected $lookups=array();

    /**
     * Ajoute un LookupHelper
     *
     * @param LookupHelper $item
     */
    public function add(LookupHelper $lookup)
    {
        $this->lookups[] = $lookup;
    }

    /**
     * @inheritdoc
     */
    protected function getEntries(array & $result, array & $sortKeys)
    {
        foreach($this->lookups as $lookup)
        {
            $lookup->max = $this->max;
            $lookup->format = $this->format;
            $lookup->words = $this->words;
            if ($this->debug)
                echo get_class($lookup), "::getEntries(), max=<code>$this->max</code>, format=<code>$this->format</code><blockquote>";
            $lookup->getEntries($result, $sortKeys);
            if ($this->debug)
                echo '</blockquote>';
        }
    }
}