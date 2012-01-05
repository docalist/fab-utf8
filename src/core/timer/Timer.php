<?php
/**
 * @package     fab
 * @subpackage  timer
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Timer.php 922 2008-11-27 16:28:47Z daniel.menard.bdsp $
 */

/**
 * Chonom�trage du temps d'ex�cution du code.
 * 
 * Timer est une classe statique permettant de mesurer le temps d'ex�cution de
 * certaines sections de code.
 * 
 * Les sections poss�dent un nom et sont d�finies par des appels aux m�thodes 
 * {@link enter() Timer::enter()} et {@link leave() Timer::leave()}.
 *  
 * Remarque : 
 * Si vous n'indiquez pas de nom pour une section, Timer attribuera 
 * automatiquement le nom de la fonction ou de la m�thode dans laquelle vous 
 * �tes.
 * 
 * Les sections peuvent �tre imbriqu�es les unes dans les autres � l'infini. 
 * Cela permet d'obtenir plus de d�tails sur la mani�re dont une section de code
 * s'ex�cute.
 * 
 * Important :
 * Les appels � {@link enter() Timer::enter()} et {@link leave() Timer::leave()}
 * doivent toujours fonctionner par paire : si vous ouvrez une section mais 
 * que vous oubliez de la fermer (ou que vous fites l'inverse), les r�sultats 
 * obtenus n'auront aucun sens. 
 * 
 * Exemple d'utilisation :
 * <code>
 * function databaseRequest()
 * {
 *     Timer::enter();
 *         Timer::enter('Ouverture de la base');
 *             ...
 *         Timer::leave();
 *         ...
 *         Timer::enter('Ex�cution');
 *             ...
 *         Timer::leave();
 *
 *         Timer::enter('Fermeture de la base');
 *             ...
 *         Timer::leave();
 *     Timer::leave();
 *     Timer::enter('Ecriture des logs');
 *         ...
 *     Timer::leave();
 * }
 * </code>
 * 
 * Lorsque l'application est termin�e, il suffit d'appeller 
 * {@link printOut() Timer::printOut()} pour afficher le temps d'ex�cution de 
 * toutes les sections qui ont �t� chronom�tr�es.
 * 
 * L'affichage obtenu a la forme suivante :
 * <code>
 * - Total : 180 ms (100%)
 *     - databaseRequest() : 100 ms (55%)
 *         - Ouverture de la base : 15 ms (8 %)
 *         - Ex�cution : 80 ms (44%)
 *         - Ouverture de la base : 2 ms (1 %)
 *     - Ecriture des logs : 40 ms (22%)
 * </code>
 * 
 * Pour chaque section, {@link printOut() Timer::printOut()} affiche :
 * - le nom de la section,
 * - le dur�e d'ex�cution de la section,
 * - un pourcentage repr�sentant le rapport entre le temps d'ex�cution de cette
 *   section et le temps total d'ex�cution indiqu� en premi�re ligne.
 * 
 * Remarque :
 * Si vous additionnez les temps d'ex�cution ou les pourcentages, vous 
 * n'obtiendrez pas le total. C'est normal, car les sections ne mesurent que le 
 * temps �coul� entre les appels � {@link enter() Timer::enter()} et � 
 * {@link leave() Timer::leave()}, pas ce qui se passe ailleurs.
 * 
 * @package     fab
 * @subpackage  timer
 */
abstract class Timer
{
    /**
     * La section en cours.
     *
     * @var TimerSection
     */
    private static $current=null;
    
    /**
     * R�initialise la classe Timer.
     * 
     * Cette m�thode supprime toutes les sections en cours et r�initialise la
     * classe Timer comme elle �tait au d�marrage de l'application.
     * 
     * Il est peu probable que vous ayez � utiliser cette m�thode dans votre
     * application.
     * 
     * @param timestamp $time par d�faut, l'heure de d�but du timer est
     * l'heure en cours. Time permet de faire d�marrer la section � une heure 
     * ant�rieure.
     */
    public static function reset($time=null)
    {
        self::$current=new TimerSection('Total', null, $time);
    }

    /**
     * Commence une nouvelle section.
     *
     * @param string $name le nom de la section qui d�marre. Si vous n'indiquez
     * pas de nom de section, le nom de la m�thode appellante est utilis�.
     * @param timestamp $time par d�faut, l'heure de d�but de la section est
     * l'heure en cours. Time permet de faire d�marrer la section � une heure 
     * ant�rieure.
     */
    public static function enter($name=null, $time=null)
    {
        self::$current=self::$current->start($name, $time);
    }

    /**
     * Termine la section en cours.
     * 
     * @param string $name (optionnel) nom �ventuel de la section. Si vous 
     * indiquez un nom, il doit s'agir exactement du m�me nom que celui utilis� 
     * lors de l'appel � start (utile pour r�soudre une erreur de s�quen�age).
     */
    public static function leave($name=null)
    {
        self::$current=self::$current->stop($name);
    }
    
    
    /**
     * Retourne la section en cours.
     * 
     * Lorsque toutes les sections ont �t� ferm�es (typiquement, � la fin du
     * programme), la m�thode get() retourne le timer global utilis� pour 
     * chronom�trer l'application.
     *
     * @return TimerSection la section en cours.
     */
    public static function get()
    {
        $timer=self::$current;
        while (!is_null($parent=$timer->getParent())) $timer=$parent;
        return $timer;
    }
    
    /**
     * Affiche le temps d'ex�cution de toutes les sections enregistr�es.
     * 
     * @param bool $html true pour g�n�rer une sortie html, false pour une
     * sortie texte brute.
     */
    public static function printOut($html=true)
    {
        if ($html) 
            echo '<div style="background-color: #fff; color: #000; border: 1px solid #000; padding: 5px; font-size: 12px;">';
        else 
            echo "\n";
            
        self::get()->printOutSection(null, $html);
        
        if ($html) 
            echo '</div>';
        else 
            echo "\n";
    }
}

// Initialise la classe Timer
Timer::reset();

/**
 * Mesure le temps d'ex�cution d'une section de code.
 * 
 * TimerSection est la classe utilis�e par {@link Timer} pour mesurer le temps
 * d'ex�cution.
 *
 * Chaque instance de cette classe repr�sente le temps d'ex�cution d'une section
 * unique et poss�de un nom ({@link getName()}), une dur�e d'ex�cution 
 * ({@link getElapsedTime()}) et �ventuellement des sous-sections 
 * ({@link getSections()}).
 *   
 * @package     fab
 * @subpackage  timer
 */
final class TimerSection extends Timer
{
    /**
     * La section parente de cette section
     *
     * @var TimerSection
     */
    private $parent=null;
    
    /**
     * Le nom de la section
     *
     * @var string
     */
    private $name=null;
    
    /**
     * L'heure de d�but d'ex�cution
     *
     * @var timestamp
     */
    private $startTime=null;
    
    /**
     * L'heure de fin d'ex�cution
     *
     * @var timestamp
     */
    private $endTime=null;
    
    /**
     * Les sous-sections �ventuelles
     *
     * @var array
     */
    private $children=array();

    /**
     * Constructeur
     *
     * @param string $name le nom de la section.
     * @param TimerSection $parent la section parente.
     * @param timestamp $time le timestamp de d�but de la section (optionnel, 
     * microtime() sera utilis� si le param�tre n'est pas fourni).
     */
    protected function __construct($name, $parent=null, $time=null)
    {
        $this->parent=& $parent;
        $this->name=$name;
        $this->startTime=is_null($time) ? microtime(true) : $time;
    }
    
    /**
     * Retourne le nom de la section.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * Retourne la dur�e d'ex�cution de la section.
     * 
     * Par d�faut, la m�thode retourne le temps �coul� entre l'appel � 
     * {@link Timer::start()} et l'appel � {@link Timer::leave()}.
     * 
     * Si la section n'est pas termin�e (i.e. {@link Timer::leave()} n'a 
     * pas encore �t� appell�e), la m�thode retourne le temps �coul� entre 
     * l'appel � {@link Timer::start()} et maintenant. 
     *
     * @return timestamp le temps �coul� (sous la forme d'un r�el contenant
     * les secondes dans la partie enti�re et les en microsecondes dans sa 
     * partie d�cimale).
     */
    public function getElapsedTime()
    {
        return (is_null($this->endTime) ? microtime(true) : $this->endTime) - $this->startTime;        
    }
    
    /**
     * Retourne les sous-sections qui composent la section.
     * 
     * @return array un tableau d'objets TimerSection ou un tableau vide si
     * la section ne comporte pas de sous-sections.
     */
    public function getSections()
    {
        return $this->children;
    }
    
    /**
     * Retourne la section parente de la section.
     *
     * @return TimerSection|null la section parente ou null si la section n'a pas
     * de parent.
     */
    public function getParent()
    {
        return $this->parent;
    }
    
    /**
     * D�marre une nouvelle sous-section.
     *
     * @param string $name le nom de la sous-section
     * @param timestamp $time par d�faut, l'heure de d�but de la section est
     * l'heure en cours. Time permet de faire d�marrer la section � une heure 
     * ant�rieure.
     * @return TimerSection la sous-section cr��e.
     */
    protected function & start($name=null, $time=null)
    {
        if (is_null($name))
        {
            $stack=debug_backtrace();
            $name=$stack[2]['class']. '.' .$stack[2]['function']; 
        }
        $timer=new TimerSection($name, $this, $time);
        $this->children[]=& $timer;
        return $timer;
    }
    
    /**
     * Termine la section.
     *
     * @param string $name (optionnel) nom �ventuel de la section. Si vous 
     * indiquez un nom, il doit s'agir exactement du m�me nom que celui utilis� 
     * lors de l'appel � start (utile pour r�soudre une erreur de s�quen�age).
     * 
     * @return TimerSection|null la section parente de cette section ou null si
     * la section n'a pas de parent. 
     */
    protected function & stop($name=null)
    {
        if (! is_null($name))
        {
            if ($name !== $this->name)
                die('erreur de s�quen�age. Nom attendu : '.$this->name.', nom indiqu� : '.$name);
        }
        $this->endTime=microtime(true);
        return $this->parent;
    }

    /**
     * Affiche le temps �coul� pour la section et pour chacune de ses 
     * sous-sections. 
     *
     * @param timestamp|null $total le temps total � utiliser pour le calcul
     * des pourcentages.
     */
    protected function printOutSection($total=null, $html=true, $indent=0)
    {
        if (is_null($this->endTime))
            $elapsed=microtime(true)-$this->startTime;
        else
            $elapsed=$this->endTime-$this->startTime;

        if (is_null($total)) $total=$elapsed;
        
        $nl= $html ? "<br />\n" : "\n";
        $nbsp= $html ? '&#160;' : ' ';
        $strong=$html ? '<strong>%s</strong>' : '%s';
        $bar=$html ? '<code>[%s%s]</code>' : '[%s%s]';
        
        $percent=($elapsed/$total)*100;
        $bars=round(10*$percent/100);
        if ($bars<0) echo 'PPPPPP';
        $percent=round($percent,1);
        printf($bar, str_repeat('=', $bars), str_repeat($nbsp, 10-$bars));
        echo str_repeat($nbsp, $indent), ' ', $this->name, ' : ';
        printf($strong, Utils::friendlyElapsedTime($elapsed));
        echo ' (', $percent, ' %)', $nl;
        
        if ($this->children)
        {
            //echo '<ul>';
            foreach($this->children as $timer)
            {
                $timer->printOutSection($total, $html, $indent+4);
            }
            //echo '</ul>';
        }
        //echo '</li>';
    }
}
?>