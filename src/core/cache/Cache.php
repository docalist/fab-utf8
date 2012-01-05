<?php
/**
 * @package     fab
 * @subpackage  cache
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Cache.php 782 2008-06-19 07:20:51Z daniel.menard.bdsp $
 */

/**
 * Gestionnaire de cache.
 * 
 * Fab int�gre un syst�me de cache qui permet de stocker sous forme de fichier
 * la version compil�e des {@link Config fichiers de configuration} et des 
 * {@link Template templates} utilis�s dans l'application.
 * 
 * Ce syst�me am�liore grandement les performances de fab : charger un fichier 
 * xml, le valider et extraire les valeurs indiqu�es est une op�ration qui prend
 * du temps. Les performances obtenues seraient m�diocres si ce traitement 
 * devait �tre fait � chaque fois, pour chaque requ�te et pour chacun des 
 * fichiers de configuration utilis�s.
 * 
 * Il en va de m�me pour les templates qui peuvent contenir toute une vari�t�
 * de tags et de variables diff�rents, des templates match, des slots, etc.
 *    
 * Avec le gestionnaire de cache de fab, lorsqu'un fichier est utilis� pour la 
 * premi�re fois, il est charg�, analys� et compil� pour produire un fichier 
 * contenant du code source PHP optimis� contenant la m�me information que celle 
 * figurant dans le fichier d'origine.
 * 
 * Lors des utilisations suivantes, l'application regarde si la version compil�e
 * du fichier demand� figure ou non dans le cache. Si c'est le cas, et que 
 * celui-ci est � jour, le fichier PHP est directement utilis� avec un simple
 * {@link http://php.net/include include}.
 * 
 * Remarque : 
 * Le fonctionnement de ce syst�me de cache a �t� con�u pour �tre compatible 
 * avec des acc�l�rateurs de code PHP tels que {@link http://php.net/apc APC},
 * ce qui permet d'am�liorer encore plus les performances.
 * Dans ce cas, d�s la premi�re utilisation, le 
 * {@link http://fr.wikipedia.org/wiki/Bytecode byte code} de la version compil�e 
 * sera stock� en m�moire partag�e par l'acc�l�rateur de code et sera 
 * imm�diatement utilisable par les requ�tes suivantes, sans qu'il soit 
 * n�cessaire de charger le fichier php.
 * 
 * Par d�faut, le syst�me de cache v�rifie syst�matiquement que le fichier 
 * original n'a pas �t� modifi� depuis la derni�re compilation.
 * 
 * Configuration :
 * - La configuration du syst�me de cache se trouve dans le fichier 
 *   {@link /AdminFiles/Edit?directory=config&file=config.php config.php}
 *   du r�pertoire {@link /AdminConfig /config}.
 * 
 * Emplacement et hi�rarchie du cache : 
 * - Par d�faut, fab d�termine automatiquement le path du r�pertoire dans lequel
 *   seront stock�s les fichiers compil�s mais il est possible d'indiquer un 
 *   r�pertoire dans la cl� <code>path</code> du fichier de config.
 * 
 * - Au sein de ce r�pertoire, fab va cr�er un r�pertoire pour chacun des 
 *   environnements existants pour l'application (normal, debug, test, etc.)
 * 
 * - Chacun de ces r�pertoires contient ensuite un r�pertoire pour les fichiers
 *   de fab (r�pertoire <code>fab</code>) et un r�pertoire pour les fichiers 
 *   de l'application (r�pertoire <code>app</code>).
 * 
 * - On retrouve ensuite la m�me hi�rarchie de fichiers que dans l'application
 *   et dans fab (le cache utilise le chemin relatif du fichier � compiler pour
 *   d�terminer le path exact du fichier mis en cache).
 * 
 * Remarque :
 * fab dispose d'un {@link /AdminCache module d'administration} nomm� 
 * {@link AdminCache} qui permet de visualiser et d'effacer tout ou partie des 
 * fichiers pr�sents dans le cache.
 * 
 * @package     fab
 * @subpackage  cache
 */

final class Cache
{
    /**
     * La liste des caches g�r�s par le gestionnaire de cache.
     * 
     * Chaque item du tableau est un tableau contenant deux �l�ments :
     * - le r�pertoire racine des fichiers qui seront mis en cache ;
     * - le r�pertoire � utiliser pour la version en cache des fichiers.
     * 
     * @var array 
     */
    private static $caches=array();
    
    
    /**
     * Constructeur.
     * 
     * Le constructeur est priv� : il n'est pas possible d'instancier la
     * classe. Utilisez directement les m�thodes statiques propos�es.
     */
    private function __construct()
    {
    }

    
    /**
     * Cr�e un nouveau cache.
     * 
     * @param string $root la racine des fichiers qui pourront �tre stock�s
     * dans ce cache. Seuls les fichiers dont le path commence par <code>$root</code>
     * pourront �tre stock�s.
     * 
     * @param string $cacheDir le path du r�pertoire dans lequel les fichiers
     * de cache seront stock�s.
     * 
     * @return bool true si le cache a �t� cr��, false dans le cas contraire
     * (droits insuffisants pour cr�er le r�pertoire, chemin erron�...)
     */
    public static function addCache($root, $cacheDir)
    {
        $root=rtrim($root,'/\\') . DIRECTORY_SEPARATOR;
        $cacheDir=rtrim($cacheDir,'/\\') . DIRECTORY_SEPARATOR;
        if (! is_dir($cacheDir))
        {
            if (! Utils::makeDirectory($cacheDir)) return false;
        }
        else
        {
        	if (! is_writable($cacheDir)) return false;
        }
            
        self::$caches[]=array($root, $cacheDir);
        return true;
    }
    
    
    /**
     * Retourne le path de la version en cache du fichier dont le path est 
     * pass� en param�tre.
     * 
     * @param string $path le path du fichier qui sera lu ou �crit dans le cache.
     * 
     * @param int $cacheNumber une variable optionnelle permettant de r�cup�rer
     * le num�ro interne du cache contenant le path indiqu�.
     * 
     * @return string le path de la version en cache de ce fichier.
     * 
     * @throws Exception si le fichier indiqu� ne peut pas figurer dans le
     * cache.
     */
    public static function getPath($path, &$cacheNumber=null)
    {
        foreach(self::$caches as $cacheNumber=>$cache)
        {
        	$root=& $cache[0];
            if (strncasecmp($root, $path, strlen($root))==0)
                return $cache[1] . substr($path, strlen($root))/* . ".php"*/;
        }
        throw new Exception("Le fichier '$path' ne peut pas figurer dans le cache");
    }

    
    /**
     * Indique si un fichier figure ou non dans le cache et s'il est � jour.
     * 
     * @param string $path le path du fichier � tester.
     * 
     * @param timestamp $minTime date/heure minimale du fichier pr�sent dans le 
     * cache pour qu'il soit consid�r� comme � jour.
     * 
     * @return bool true si le fichier est dans le cache et est � jour, false 
     * sinon.
     */
    public static function has($path, $minTime=0)
    {
        if (! file_exists($path=self::getPath($path))) return false;
        return ($minTime==0) || (filemtime($path) > $minTime);
    }

    
    /**
     * Retourne la date de derni�re modification d'un fichier pr�sent dans le 
     * cache.
     * 
     * @param string $path le path du fichier dont on veut conna�tre la date de
     * derni�re modification.
     * 
     * @return timestamp la date/heure de derni�re modification du fichier ou 
     * z�ro si le fichier n'est pas pr�sent dans le cache.
     */
    public static function lastModified($path)
    {
        return (file_exists($path=self::getPath($path)) ? filemtime($path) : 0);
    }

    
    /**
     * Stocke la version compil�e d'un fichier dans le cache.
     * 
     * Le path indiqu� peut contenir des noms de r�pertoires, ceux-ci seront 
     * cr��s s'il y a lieu.
     *  
     * @param string $path le path du fichier � stocker.
     * 
     * @param string $data le contenu du fichier � stocker.
     * 
     * @return bool true si le fichier a �t� mis en cache, false sinon (erreur
     * d'�criture, droits insuffisants...)
     */
    public static function set($path, $data)
    {
        $path=self::getPath($path);
        if (! is_dir($dir=dirname($path))) 
            if (! Utils::makeDirectory($dir))
                return false;
         // Cr��e les fichiers avec tous les droits.
         // Raison : lors d'une mise � jour d'apache, le 'user' utilis� par le daemon apache peut changer (daemon, nobody, www-data...)
         // Si des fichiers sont cr��s dans le cache par l'utilisateur 'daemon', par exemple, et que par la suite, on passe �
         // 'www-data', on ne pourra pas �craser le fichier existant.
         // En faisant umask(0), tout le monde a les droits en lecture/�criture
        umask(0);
        return (false !== @file_put_contents($path, $data, LOCK_EX));
    }

    
    /**
     * Charge la version compil�e d'un fichier � partir du cache.
     * 
     * @param string $path le path du fichier � charger.
     * 
     * @return string les donn�es lues ou false si le fichier n'existe
     * pas ou ne peut pas �tre lu.
     */
    public static function get($path)
    {
        return @file_get_contents(self::getPath($path));
    }

    
    /**
     * Supprime un fichier du cache.
     * 
     * Aucune erreur n'est g�n�r�e si le fichier indiqu� ne figure pas dans 
     * le cache.
     * 
     * La fonction essaie �galement de supprimer tous les r�pertoires, d�s lors 
     * que ceux-ci sont vides.
     * 
     * @param string $path le path du fichier � supprimer du cache.
     */
    public static function remove($path)
    {
        $index=0; // �vite 'var not initialized' sous eclipse 
        $path=self::getPath($path, $index);
         
        @ unlink($path);
        $minLen = strlen(self::$caches[$index][1]);
        for (;;)
        {
            $path = dirname($path);
            if (strlen($path) < $minLen)
                break;

            if (!@ rmdir($path))
                break;
        }
    }

    
    /**
     * Vide le cache
     * 
     * clear permet de nettoyer le cache en supprimant soit tous les
     * fichiers, soit les fichiers ant�rieurs � une date de donn�e.
     * 
     * On peut soit vider le cache en entier, soit sp�cifier un sous-r�pertoire
     * � partir duquel le nettoyage commence.
     * 
     * La fonction tente �galement de supprimer tous les r�pertoires vides.
     * 
     * Lorsque l'int�gralit� du cache est vid�e, le r�pertoire cacheDirectory
     * est lui-m�me supprim�.
     * 
     * @param  string  $path r�pertoire � partir duquel il faut commencer le
     * nettoyage, ou une chaine vide pour nettoyer l'int�gralit� du cache.
     * @param  $minTime  date/heure minimale des fichiers � supprimer. Tous 
     * les fichiers dont la date de derni�re modification est inf�rieure ou 
     * �gale � l'heure sp�cifi�e seront supprim�s. Indiquer z�ro (c'est la 
     * valeur par d�faut) pour supprimer tous les fichiers.
     * @return boolean true si le cache (ou tout au moins la partie sp�cifi�e 
     * par $path) a �t� enti�rement vid�. Il est normal que la fonction retourne 
     * false lorsque vous mentionnez le param�tre $minTime : cela signifie
     * simplement que certains fichiers, plus r�cents que l'heure indiqu�e
     * n'ont pas �t� supprim�s.
    */
//    public static function clear($path = '', $minTime = 0)
//    {
//        // Cr�e un path absolu et garantit qu'on vide uniquement des r�pertoires du cache
//        if (! $cd = self :: $cacheDirectory) 
//            die("Le cache n'a pas �t� initialis�. Utilisez Cache:setCacheDir");
//        
//        if (substr($path, 0, strlen($cd)) != $cd)
//            $path = Utils :: makePath($cd, $path);
//
//        if (!($dir = opendir($path)))
//            die("Impossible d'ouvrir le r�pertoire $dir");
//
//        $result = true;
//        while ($file = readdir($dir))
//        {
//            if (($file != '.') && ($file != '..'))
//            {
//                $file2 = Utils :: makePath($path, $file);
//                if (is_file($file2))
//                {
//                    if ($minTime == 0 || (filemtime($file2) <= $minTime))
//                        $result = $result && @ unlink($file2);
//                }
//                elseif (is_dir($file2))
//                    $result = $result and (self :: clear($file2, $minTime));
//            }
//        }
//        @rmdir($path);
//        closedir($dir);
//        return $result;
//    }

}
?>