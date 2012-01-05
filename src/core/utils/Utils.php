<?php
/**
 * @package     fab
 * @subpackage  util
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Utils.php 1234 2010-12-14 11:20:33Z daniel.menard.bdsp $
 */

/**
 * Fonctions utilitaires
 *
 * @package     fab
 * @subpackage  util
 */
final class Utils
{
	/**
	 * constructeur
	 *
	 * Le constructeur est priv� : il n'est pas possible d'instancier la
	 * classe. Utilisez directement les m�thodes statiques propos�es.
     *
     * @access private
	 */
	private function __construct()
	{
	}


	/**
	 * Retourne la partie extension d'un path
	 *
	 * @param string $path
	 * @return string l'extension du path ou une chaine vide
	 */
	public static function getExtension($path)
	{
		$pt = strrpos($path, '.');

		if ($pt === false)
			return '';
		$ext = substr($path, $pt);
		if (strpos($ext, '/') === FALSE && strpos($ext, '\\') === FALSE)
			return $ext;
		return '';
	}


	/**
	 * Ajoute une extension au path indiqu� si celui-ci n'en a pas
	 *
	 * @param string $path le path � modifier
     *
	 * @param string $ext l'extension � ajouter
     *
	 * @return string le nouveau path
	 */
	public static function defaultExtension($path, $ext)
	{
		if (self :: getExtension($path) === '')
			$path .= ($ext{0} == '.' ? $ext : ".$ext");
		return $path;
	}


	/**
	 * Remplace ou supprime l'extension de fichier d'un path.
	 * Ne touche pas aux extensions pr�sentes dans les noms de r�pertoires
	 * (c:\toto.tmp\aa.jpg).
	 * G�re � la fois les slashs et les anti-slashs
	 *
	 * @param string $path le path � modifier
     *
	 * @param string $ext l'extension � appliquer � $path, ou vide pour supprimer
	 * l'extension existante. $ext peut �tre indiqu� avec ou sans point de d�but
	 */
	public static function setExtension($path, $ext = '')
	{
		if ($ext && $ext {0} != '.')
		  $ext = ".$ext";

		$pt = strrpos($path, '.');

		if ($pt !== false)
		{
			$oldext = substr($path, $pt);
			if (strpos($oldext, '/') === FALSE && strpos($oldext, '\\') === FALSE)
			{
				$path = substr($path, 0, $pt) . $ext;
				return $path;
			}
		}
		$path = $path . $ext;
		return $path;
	}


	/**
	 * Cr�e le r�pertoire indiqu�
	 *
	 * La fonction cr�e en une seule fois tous les r�pertoires n�cessaires du
	 * niveau le plus haut au plus bas.
	 *
	 * Le r�pertoire cr�� a tous les droits (777).
	 *
	 * @param string $path le chemin complet du r�pertoire � cr�er
     * @return bool true si le r�pertoire a �t� cr��, false sinon
     * (droits insuffisants, par exemple)
	 */
	public static function makeDirectory($path)
	{
        if (is_dir($path)) return true;
		umask(0);
		return @mkdir($path, 0777, true);
	}


	/**
	 * Indique si le path pass� en param�tre est un chemin relatif
	 *
	 * Remarque : aucun test d'existence du path indiqu� n'est fait.
	 *
	 * @param string $path le path � tester
     *
	 * @return bool true si path est un chemin relatif, false sinon
	 */
	public static function isRelativePath($path)
	{
		if (!$len = strlen($path)) return true;
		if (strpos('/\\', $path{0}) !== false) return false;
		if ($len > 2 && $path{1} == ':') return false;
		return true;
	}


	/**
	 * Indique si le path pass� en param�tre est un chemin absolu
	 *
	 * Remarque : aucun test d'existence du path indiqu� n'est fait.
	 *
	 * @param string $path le path � tester
     *
	 * @return bool true si path est un chemin absolu, false sinon
	 */
	public static function isAbsolutePath($path)
	{
		return !self :: isRelativePath($path);
	}


	/**
	 * Construit un chemin complet � partir des bouts pass�s en param�tre.
	 *
	 * La fonction concat�ne ses arguments en prenant soin d'ajouter
	 * correctement le s�parateur s'il est n�cessaire.
	 *
	 * Exemple :
	 * <code>
	 * makePath('a','b','c'); // 'a/b/c'
	 * makePath('/temp/','/dm/','test.txt'); // '/temp/dm/test.txt'
	 * </code>
	 *
	 * Le path obtenu n'est pas normalis� : si les arguments pass�s contiennent
	 * des '.' ou des '..' le r�sultat les contiendra aussi.
     *
     * Le s�parateur de r�pertoire, par contre, est normalis� (slashs et
     * anti-slash sont remplac�s par le s�parateur du syst�me h�te.)
	 *
	 * @param string paramname un nombre variable d'arguments � concat�ner
     *
	 * @return string le path obtenu
	 */
	public static function makePath()
	{
		$path = '';
        $t=func_get_args();
		foreach ($t as $arg)
		{
            $arg = strtr($arg, '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR);
			if ($path)
			{
				if ($arg && ($arg[0] === DIRECTORY_SEPARATOR))
					$path = rtrim($path, DIRECTORY_SEPARATOR).$arg;
				else
					$path = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$arg;
			}
			else
			{
				$path = $arg;
			}
		}
		return $path;
	}


	/**
	 * Nettoie un path, en supprimant les parties '.' et '..' inutiles.
	 *
	 * Exemple :
	 * <code>
	 * cleanPath('/a/b/../c/') -> '/a/c/'
	 * </code>
	 *
	 * La fonction ne supprime que les parties '..' qui sont r�solvables, ce qui
	 * peut �viter certains attaques (acc�der � un r�pertoire au dessus du
	 * r�pertoire 'root', par exemple).
	 *
	 * Exemple :
	 * <code>
	 * cleanPath('/a/../../') -> '/../'
	 * </code>
	 *
	 * Pour savoir si le path obtenu est propre, c'est-�-dire si toutes les
	 * r�f�rences '..' ont �t� r�solues, utiliser {@link isCleanPath()} apr�s.
	 *
	 * @param string $path le path � normaliser
     *
	 * @return string le path normalis�
	 */
	public static function cleanPath($path)
	{
        if (strlen($path) > 2 && $path{1} == ':')
		{
			$prefix = substr($path, 0, 2);
			$path = substr($path, 2);
		}
		else
			$prefix = '';

		$path = preg_replace('~[/\\\\]+~', DIRECTORY_SEPARATOR, $path);
		$parts = explode(DIRECTORY_SEPARATOR, $path);
		$t = array ();

		foreach ($parts as $dir)
		{
			if ($dir == '.')
				continue;

			$last = end($t);
			if ($dir == '..' && $last != '' && $last != '..')
				array_pop($t);
			else
				$t[] = $dir;
		}
		$path = $prefix . implode(DIRECTORY_SEPARATOR, $t);
		return $path;

	}


	/**
	 * Indique si le path fourni contient des �l�ments '.' ou '..'
	 *
	 * @param string $path le path � tester
     *
	 * @return bool vrai si le path est propre, faux sinon.
	 */
	public static function isCleanPath($path)
	{
		return preg_match('~[/\\\\]\.\.?[/\\\\]|^\.\.|\.\.$~', $path) ? false : true;
	}


    /**
     * Recherche un fichier dans une liste de r�pertoires. Les r�pertoires sont
     * examin�s dans l'ordre o� ils sont fournis.
     *
     * @param string $file le fichier � chercher. Vous pouvez soit indiquer un
     * simple nom de fichier (par exemple 'test.php') ou bien un 'bout' de
     * chemin ('/modules/test.php')
     *
     * @param mixed $directory... les autres param�tres indiquent les
     * r�pertoires dans lesquels le fichier sera recherch�.
     *
     * @return mixed false si le fichier n'a pas �t� trouv�, le
     * chemin exact du fichier dans le cas contraire.
     */
    public static function searchFile($file /* , $directory1, $directory2... $directoryn */)
    {
        $nb=func_num_args();
        for ($i=1; $i<$nb; $i++)
        {
            $dir=func_get_arg($i);
            if (false !== $path=Utils::realpath(rtrim($dir,'/\/').DIRECTORY_SEPARATOR.$file))
                return $path;
        }

        foreach(self::$searchPath as $dir)
            if (false !== $path=Utils::realpath($dir.DIRECTORY_SEPARATOR.$file))
                return $path;

        return false;
    }

    static $searchPath=array();
    public static function clearSearchPath()
    {
        self::$searchPath=array(Utils::realpath(Runtime::$fabRoot.'core/template'));
        // HACK: le fait de toujours avoir core/template dans le chemin est un hack
        // il faudrait g�rer des "�tats" et pouvoir revenir � un �tat donn�
    }
    public static function addSearchPath($path)
    {
    	array_unshift(self::$searchPath,$path);
//        echo "<pre>add searchPath $path array=", var_export(self::$searchPath,true), '</pre><br />';
    }

    /**
     * Recherche un fichier dans une liste de r�pertoires, sans tenir compte de la
     * casse du fichier recherch�.
     *
     * Les r�pertoires sont examin�s dans l'ordre o� ils sont fournis.
     *
     * @param string $file le fichier � chercher. Vous pouvez soit indiquer un
     * simple nom de fichier (par exemple 'test.php') ou bien un 'bout' de
     * chemin ('/modules/test.php')
     *
     * @param mixed $directory... les autres param�tres indiquent les
     * r�pertoires dans lesquels le fichier sera recherch�.
     *
     * @return mixed false si le fichier n'a pas �t� trouv�, le
     * chemin exact du fichier dans le cas contraire.
     */
    public static function searchFileNoCase($file /* , $directory1, $directory2... $directoryn */)
    {
        $nb=func_num_args();
        for ($i=1; $i<$nb; $i++)
        {
            $dir=rtrim(func_get_arg($i), '/\\');
            if (is_dir($dir) &&  false !== $handle=opendir($dir)) // si le r�pertoire n'existe pas, on ignore
            {
                while (($thisFile=readdir($handle)) !==false)
                {
                	if (strcasecmp($file, $thisFile)==0)
                    {
                        // pas de test && is_dir($thisFile)
                        // faut �tre tordu pour mettre dans le m�me r�pertoire
                        // un fichier et un sous-r�pertoire portant le m�me nom

                        closedir($handle);
                        return Utils::realpath($dir . DIRECTORY_SEPARATOR . $thisFile);
                    }
                }
                closedir($handle);
            }
        }
        return false;
    }


    // TODO: doc � �crire
    public static function convertString($string, $table='bis')
    {
        static $charFroms=null, $tables=null;

        if (is_null($charFroms))
        {
            $charFroms=
                    /*           0123456789ABCDEF*/
                    /* 00 */    "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f".
                    /* 10 */    "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f".
                    /* 20 */    "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f".
                    /* 30 */    "\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f".
                    /* 40 */    "\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f".
                    /* 50 */    "\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f".
                    /* 60 */    "\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f".
                    /* 70 */    "\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f".
                    /* 80 */    "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f".
                    /* 90 */    "\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f".
                    /* A0 */    "\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf".
                    /* B0 */    "\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf".
                    /* C0 */    "\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf".
                    /* D0 */    "\xd0\xd1\xd2\xd3\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf".
                    /* E0 */    "\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8\xe9\xea\xeb\xec\xed\xee\xef".
                    /* F0 */    "\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf7\xf8\xf9\xfa\xfb\xfc\xfd\xfe\xff";

            $tables=array
            (
                'bis'=>
                    /*           0123456789ABCDEF*/
                    /* 00 */    '                '.
                    /* 10 */    '                '.
                    /* 20 */    '                '.
                    /* 30 */    '0123456789      '.
                    /* 40 */    '@abcdefghijklmno'.
                    /* 50 */    'pqrstuvwxyz     '.
                    /* 60 */    ' abcdefghijklmno'.
                    /* 70 */    'pqrstuvwxyz     '.
                    /* 80 */    '                '.
                    /* 90 */    '                '.
                    /* A0 */    '                '.
                    /* B0 */    '                '.
                    /* C0 */    'aaaaaaaceeeeiiii'.
                    /* D0 */    'dnooooo 0uuuuy s'.
                    /* E0 */    'aaaaaaaceeeeiiii'.
                    /* F0 */    'dnooooo  uuuuyby',

                'queryparser'=>
                    /*           0123456789ABCDEF*/
                    /* 00 */    '                '.
                    /* 10 */    '     �          '.
                    /* 20 */    '  "     ()*+ -. '.
                    /* 30 */    '0123456789:  :  '.
                    /* 40 */    '@abcdefghijklmno'.
                    /* 50 */    'pqrstuvwxyz[ ] _'.
                    /* 60 */    ' abcdefghijklmno'.
                    /* 70 */    'pqrstuvwxyz     '.
                    /* 80 */    '                '.
                    /* 90 */    '                '.
                    /* A0 */    '                '.
                    /* B0 */    '                '.
                    /* C0 */    'aaaaaaaceeeeiiii'.
                    /* D0 */    'dnooooo 0uuuuy s'.
                    /* E0 */    'aaaaaaaceeeeiiii'.
                    /* F0 */    'dnooooo  uuuuyby',

                'lower'=>
                    /*           0123456789ABCDEF*/
                    /* 00 */    "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f".
                    /* 10 */    "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f".
                    /* 20 */    "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f".
                    /* 30 */    "0123456789\x3a\x3b\x3c\x3d\x3e\x3f".
                    /* 40 */    '@abcdefghijklmno'.
                    /* 50 */    "pqrstuvwxyz\x5b\x5c\x5d\x5e\x5f".
                    /* 60 */    "\x60abcdefghijklmno".
                    /* 70 */    "pqrstuvwxyz\x7b\x7c\x7d\x7e\x7f".
                    /* 80 */    "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f".
                    /* 90 */    "\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f".
                    /* A0 */    "\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf".
                    /* B0 */    "\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf".
                    /* C0 */    'aaaaaaaceeeeiiii'.
                    /* D0 */    'dnooooo 0uuuuy s'.
                    /* E0 */    'aaaaaaaceeeeiiii'.
                    /* F0 */    'dnooooo  uuuuyby',

                'upper'=>
                    /*           0123456789ABCDEF*/
                    /* 00 */    "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f".
                    /* 10 */    "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f".
                    /* 20 */    "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f".
                    /* 30 */    "0123456789\x3a\x3b\x3c\x3d\x3e\x3f".
                    /* 40 */    '@ABCDEFGHIJKLMNO'.
                    /* 50 */    "PQRSTUVWXYZ\x5b\x5c\x5d\x5e\x5f".
                    /* 60 */    "\x60ABCDEFGHIJKLMNO".
                    /* 70 */    "PQRSTUVWXYZ\x7b\x7c\x7d\x7e\x7f".
                    /* 80 */    "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8a\x8b\x8c\x8d\x8e\x8f".
                    /* 90 */    "\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9a\x9b\x9c\x9d\x9e\x9f".
                    /* A0 */    "\xa0\xa1\xa2\xa3\xa4\xa5\xa6\xa7\xa8\xa9\xaa\xab\xac\xad\xae\xaf".
                    /* B0 */    "\xb0\xb1\xb2\xb3\xb4\xb5\xb6\xb7\xb8\xb9\xba\xbb\xbc\xbd\xbe\xbf".
                    /* C0 */    'AAAAAAACEEEEIIII'.
                    /* D0 */    'DNOOOOO 0UUUUY S'.
                    /* E0 */    'AAAAAAACEEEEIIII'.
                    /* F0 */    'DNOOOOO  UUUUYBY',

                'CP1252 to CP850' => // Table de conversion CP1252 vers CP850 (ANSI to DOS)
                    /*          00  01  02  03  04  05  06  07  08  09  0a  0b  0c  0d  0e  0f */
                    /* 00 */ "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f" .
                    /* 10 */ "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f" .
                    /* 20 */ "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f" .
                    /* 30 */ "\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f" .
                    /* 40 */ "\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f" .
                    /* 50 */ "\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f" .
                    /* 60 */ "\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f" .
                    /* 70 */ "\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f" .
                    /* 80 */ "\x45\x81\x60\x9f\x22\x2e\x2b\x87\x5e\x6f\x53\x3c\x4f\x8d\x5a\x8f" .
                    /* 90 */ "\x90\x60\xef\x22\x22\x6f\x2d\x2d\x7e\x54\x73\x3e\x6f\x9d\x7a\x22" .
                    /* a0 */ "\xff\xad\xbd\x9c\xcf\xbe\xdd\xf5\xf9\xb8\xa6\xae\xaa\xf0\xa9\xee" .
                    /* b0 */ "\xf8\xf1\xfd\xfc\xef\xe6\xf4\xfa\xf7\xfb\xa7\xaf\xac\xab\xf3\xa8" .
                    /* c0 */ "\xb7\xb5\xb6\xc7\x8e\x8f\x92\x80\xd4\x90\xd2\xd3\xde\xd6\xd7\xd8" .
                    /* d0 */ "\xd1\xa5\xe3\xe0\xe2\xe5\x99\x9e\x9d\xeb\xe9\xea\x9a\xed\xe8\xe1" .
                    /* e0 */ "\x85\xa0\x83\xc6\x84\x86\x91\x87\x8a\x82\x88\x89\x8d\xa1\x8c\x8b" .
                    /* f0 */ "\xd0\xa4\x95\xa2\x93\xe4\x94\xf6\x9b\x97\xa3\x96\x81\xec\xe7\x98",

                'CP850 to CP1252' => // Table de conversion CP850 vers CP1252 (DOS TO ANSI)
                    /*          00  01  02  03  04  05  06  07  08  09  0a  0b  0c  0d  0e  0f */
                    /* 00 */ "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f" .
                    /* 10 */ "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f" .
                    /* 20 */ "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f" .
                    /* 30 */ "\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f" .
                    /* 40 */ "\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f" .
                    /* 50 */ "\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f" .
                    /* 60 */ "\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f" .
                    /* 70 */ "\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f" .
                    /* 80 */ "\xc7\xfc\xe9\xe2\xe4\xe0\xe5\xe7\xea\xeb\xe8\xef\xee\xec\xc4\xc5" .
                    /* 90 */ "\xc9\xe6\xc6\xf4\xf6\xf2\xfb\xf9\xff\xd6\xdc\xf8\xa3\xd8\xd7\x83" .
                    /* a0 */ "\xe1\xed\xf3\xfa\xf1\xd1\xaa\xba\xbf\xae\xac\xbd\xbc\xa1\xab\xbb" .
                    /* b0 */ "\xb0\xb1\xb2\x7c\x2b\xc1\xc2\xc0\xa9\xb9\xba\xbb\xbc\xa2\xa5\x2b" .
                    /* c0 */ "\x2b\x2b\x2b\x2b\x2d\x2b\xe3\xc3\xc8\xc9\xca\xcb\xcc\xcd\xce\xa4" .
                    /* d0 */ "\xf0\xd0\xca\xcb\xc8\x69\xcd\xce\xcf\x2b\x2b\xdb\xdc\xa6\xcc\xdf" .
                    /* e0 */ "\xd3\xdf\xd4\xd2\xf5\xd5\xb5\xfe\xde\xda\xdb\xd9\xfd\xdd\xaf\xb4" .
                    /* f0 */ "\xad\xb1\xf2\xbe\xb6\xa7\xf7\xb8\xb0\xa8\xb7\xb9\xb3\xb2\xfe\xa0",

                'CP1252 to CP437' => // Table de conversion CP1252 vers CP437
                    /*          00  01  02  03  04  05  06  07  08  09  0a  0b  0c  0d  0e  0f */
                    /* 00 */ "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f" .
                    /* 10 */ "\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f" .
                    /* 20 */ "\x20\x21\x22\x23\x24\x25\x26\x27\x28\x29\x2a\x2b\x2c\x2d\x2e\x2f" .
                    /* 30 */ "\x30\x31\x32\x33\x34\x35\x36\x37\x38\x39\x3a\x3b\x3c\x3d\x3e\x3f" .
                    /* 40 */ "\x40\x41\x42\x43\x44\x45\x46\x47\x48\x49\x4a\x4b\x4c\x4d\x4e\x4f" .
                    /* 50 */ "\x50\x51\x52\x53\x54\x55\x56\x57\x58\x59\x5a\x5b\x5c\x5d\x5e\x5f" .
                    /* 60 */ "\x60\x61\x62\x63\x64\x65\x66\x67\x68\x69\x6a\x6b\x6c\x6d\x6e\x6f" .
                    /* 70 */ "\x70\x71\x72\x73\x74\x75\x76\x77\x78\x79\x7a\x7b\x7c\x7d\x7e\x7f" .
                    /* 80 */ "\x45\x81\x27\x9f\x22\x2e\x2b\x87\x5e\x6f\x53\x3c\x4f\x8d\x5a\x8f" .
                    /* 90 */ "\x90\x27\x27\x22\x22\x6f\x2d\x2d\x7e\x54\x73\x3e\x6f\x9d\x7a\x22" .
                    /* a0 */ "\xff\xad\x9b\x9c\xa4\x9d\x7c\x53\x22\x28\xa6\xae\xaa\x2d\x28\xaf" .
                    /* b0 */ "\xf8\xf1\xfd\x5e\x27\xe6\x50\xfa\x2c\x5e\xa7\xaf\xac\xab\x20\xa8" .
                    /* c0 */ "\x60\x27\x5e\x7e\x8e\x8f\x92\x80\x60\x90\x5e\x22\x60\x27\x5e\x22" .
                    /* d0 */ "\x44\xa5\x60\x27\x5e\x7e\x99\x78\x4f\x60\x27\x5e\x9a\x27\x54\xe1" .
                    /* e0 */ "\x85\xa0\x83\x7e\x84\x86\x91\x87\x8a\x82\x88\x89\x8d\xa1\x8c\x8b" .
                    /* f0 */ "\x64\xa4\x95\xa2\x93\x7e\x94\xf6\x6f\x97\xa3\x96\x81\x27\x74\x98",

                'alphanum'=> // ne garde que les lettres et les chiffres, minusculise, supprime les accents
                    /*           0123456789ABCDEF*/
                    /* 00 */    '                '.
                    /* 10 */    '                '.
                    /* 20 */    '                '.
                    /* 30 */    '0123456789      '.
                    /* 40 */    ' abcdefghijklmno'.
                    /* 50 */    'pqrstuvwxyz     '.
                    /* 60 */    ' abcdefghijklmno'.
                    /* 70 */    'pqrstuvwxyz     '.
                    /* 80 */    '                '.
                    /* 90 */    '                '.
                    /* A0 */    '                '.
                    /* B0 */    '                '.
                    /* C0 */    'aaaaaaaceeeeiiii'.
                    /* D0 */    'dnooooo 0uuuuy s'.
                    /* E0 */    'aaaaaaaceeeeiiii'.
                    /* F0 */    'dnooooo  uuuuyby',


                'ident'=> // lettres majus, minus et chiffres (accents et caract�res de contr�les supprim�s)
                    /*           0123456789ABCDEF*/
                    /* 00 */    '                '.
                    /* 10 */    '                '.
                    /* 20 */    '                '.
                    /* 30 */    '0123456789      '.
                    /* 40 */    ' ABCDEFGHIJKLMNO'.
                    /* 50 */    'PQRSTUVWXYZ     '.
                    /* 60 */    ' abcdefghijklmno'.
                    /* 70 */    'pqrstuvwxyz     '.
                    /* 80 */    '                '.
                    /* 90 */    '                '.
                    /* A0 */    '                '.
                    /* B0 */    '                '.
                    /* C0 */    'AAAAAAACEEEEIIII'.
                    /* D0 */    'DNOOOOO 0UUUUY s'.
                    /* E0 */    'aaaaaaaceeeeiiii'.
                    /* F0 */    'dnooooo  uuuuyby',
            );
        }
        if (! isset($tables[$table]))
            throw new Exception("La table de conversion de caract�res '$table' n'existe pas");

    	return strtr($string, $charFroms, $tables[$table]);
    }

    /**
     * Cr��e une table de conversion utilisable dans la fonction convertString ci-dessus.
     *
     * La fonction utilise iconv (qui doit �tre disponible) pour g�n�rer le source php d'une
     * table permettant la conversion de caract�res SBCS (un octet=un caract�re).
     *
     * Utilisation : faire un echo du r�sultat obtenu et int�grer ce source dans la fonction
     * convertString.
     *
     * Exemple : pour cr�er une table conversion dos to ansi (plus exactement CP850 vers CP1252)
     * il suffit de faire echo createConversionTable('CP850','CP1252');
     *
     * Remarques :
     *
     * <li>l'option //TRANSLIT est ajout�e au charset de destination pour essayer de traduire
     * approximativement les caract�res qui n'ont pas de correspondance exacte.
     * Par exemple, le caract�re '�' sera traduit par 'f' pour en CP850.
     *
     * <li>les caract�res sans correspondance sont conserv�s tels quels dans la table.
     *
     * @param string $fromCharset le jeu de caract�re source
     * @param string $toCharset le jeu de caract�re destination
     * @return string l'extrait de code PHP permettant de d�finir la table
     */
    public static function createConversionTable($fromCharset, $toCharset='CP1252')
    {
        // Ajoute l'option translit au charset de destination
        $toOri=$toCharset;
        $toCharset.='//TRANSLIT';

        // V�rifie que la fonction iconv est disponible
        if (! function_exists('iconv'))
            throw new Exception("La fonction iconv n'est pas disponible");

        // V�rifie que les charset indiqu�s sont valides
        if (false===@iconv($fromCharset,$toCharset, 'a'))
            throw new Exception("L'un des charset indiqu�s n'est pas valide : '$fromCharset', '$toCharset'");

        // G�n�re l'ent�te de la table
        $table = '$table = // Table de conversion ' . $fromCharset . ' vers ' . $toOri . "\n";
        $table.= '/*          00  01  02  03  04  05  06  07  08  09  0a  0b  0c  0d  0e  0f */' . "\n";

        // G�n�re chacune des lignes
        for ($i=0; $i<16; $i++)
        {
            // G�n�re l'ent�te de la ligne
            $table .= '/* '. dechex($i). '0 */ "';

            // G�n�re les 16 valeurs de la ligne
            for ($j=0; $j<16; $j++)
            {
                // Essaie de convertir le caract�re
                $code=$i*16 + $j;
                $char=@iconv($fromCharset, $toCharset, chr($code));

                // iconv retourne '' si elle n'arraive pas � convertir le caract�re
                if ($char!=='') $code=ord($char);

                $table .= '\x'. str_pad(dechex($code), 2, '0', STR_PAD_LEFT);
            }

            // Fin de la lgne
            $table .= '"'. ($i<15?' .':';'). "\n";
        }

        // G�n�re un exemple
        $table.= '// Exemple :' . "\n";
        $h='Le c�ur d��u mais l\'�me plut�t na�ve, Lou�s r�va de crapa�ter en cano� au del� des �les, pr�s du m�lstr�m o� br�lent les nov� (http://en.wikipedia.org/wiki/Pangram)';
        $len=max(strlen($fromCharset),strlen($toOri));
        $table .= '// ' . str_pad($fromCharset,$len) . " : $h\n";
        for($i=0; $i<strlen($h);$i++)
        	if ('' !== $char=@iconv($fromCharset, $toCharset, $h[$i])) $h[$i]=$char;
        $table .= '// ' . str_pad($toOri,$len) . " : $h\n";

        for($i=128; $i<256; $i++)
            $h.=chr($i);
        // Retourne le r�sultat
        return $table;
    }


    /**
     * Met en minuscule la premi�re lettre de la chaine pass�e en param�tre et
     * retourne le r�sultat obtenu.
     *
     * @param string $str la chaine � convertir
     *
     * @return string la chaine obtenue
     */
    public static function lcfirst($str)
    {
        return strtolower(substr($str, 0, 1)) . substr($str, 1);
    }


    /**
     * Retourne la valeur de la variable pass�e en param�tre si celle-ci est
     * d�finie et contient autre chose qu'une chaine vide ou la valeur par
     * d�faut sinon.
     *
     * Remarque : la fonction repose sur le fait que la variable � examiner est
     * pass�e par r�f�rence, bien que la fonction ne modifie aucune variable. Ca
     * �vite que php g�n�re un warning indiquant que la variable n'existe pas.
     *
     * On peut appeller la fonction avec une variable simple, un tableau, un
     * �l�ment de tableau, etc.
     *
     * Remarque 2 : anyVar doit �tre une variable. �a ne marchera pas si c'est
     * un appel de fonction, une propri�t� inexistante d'un objet, une
     * constante, etc.
     *
     * Remarque 3 : �quivalent � 'empty', mais ne retourne pas vrai pour une
     * chaine contenant la valeur '0' ou pour un entier 0 ou pour un bool�en
     * false.
     *
     * @param mixed $anyVar	la variable � examiner.
     *
     * @param mixed $defaultValue la valeur par d�faut � retourner si $anyVar
     * n'est pas d�finie (optionnel, valeur par d�faut null).
     *
     * @return mixed
     */
    public static function get(&$anyVar, $defaultValue=null)
    {
        if (! isset($anyVar)) return $defaultValue;
        if (is_string($anyVar) && strlen(trim($anyVar))==0) return $defaultValue;
        if (is_bool($anyVar) or is_int($anyVar)) return $anyVar;
        if (is_float($anyVar)) return is_nan($anyVar) ? $defaultValue : $anyVar;
        if (is_array($anyVar) && count($anyVar)==0) return $defaultValue;
        return $anyVar;
    }

// idem mais nb 'illimit�' de variables pass�es par r�f�rence. pb : oblige � passer defaultvalue
// en premier ce qui est contre-intuitif.
//    public static function getAny($defaultValue, &$var1, &$var2=null, &$var3=null, &$var4=null, &$var5=null)
//    {
//        $nb=func_num_args();
//        for ($i=1; $i<$nb; $i++)
//        {
//            $arg=func_get_arg($i);
//            if
//            (
//                    isset($arg)
//                &&  (is_string($arg) && $arg != '')
//            ) return $arg;
//        }
//        return $defaultValue;
//    }


    /**
     * Retourne le path du script qui a appell� la fonction qui appelle
     * callerScript.
     *
     * Exemple : un script 'un.php' appelle une fonction test() qui se trouve
     * ailleurs. La fonction test() veut savoir qui l'a appell�. Elle appelle
     * callerScript() qui va retourner le path complet de 'un.php'
     *
     * @param int $level le nombre de parents � ignorer
     *
     * @return string
     */
    public static function callerScript($level=1)
    {
        $stack=debug_backtrace();
        // En 0, on a la trace pour la fonction qui nous a appell�
        // En 1, on a la trace de la fonction qui a appell� celle qui nous a appell�.
        // en g�n�ral, c'est �a qu'on veut, donc $level=1 par d�faut

        return $stack[$level]['file'];
    }


    public static function callerObject($level=1)
    {
        $stack=debug_backtrace();
        // En 0, on a la trace pour la fonction qui nous a appell�
        // En 1, on a la trace de la fonction qui a appell� celle qui nous a appell�.
        // en g�n�ral, c'est �a qu'on veut, donc $level=1 par d�faut

        return isset($stack[$level]['object']) ? $stack[$level]['object'] : null;
    }

    public static function callerClass($level=1)
    {
        $stack=debug_backtrace();
        // En 0, on a la trace pour la fonction qui nous a appell�
        // En 1, on a la trace de la fonction qui a appell� celle qui nous a appell�.
        // en g�n�ral, c'est �a qu'on veut, donc $level=1 par d�faut

        return @$stack[$level]['class'].@$stack[$level]['type'].@$stack[$level]['function']; // TODO: pourquoi le @ ?
    }


    public static function callLevel()
    {
        return count(debug_backtrace())-1;
    }


    /**
     * Retourne l'adresse du serveur
     * (exemple : http://www.bdsp.tm.fr)
     */
    public static function getHost()
    {
        if (Utils::get($_SERVER['HTTPS'])==='on' || Utils::get($_SERVER['HTTP_X_FORWARDED_PROTO'])==='on')
        {
            $http='https';
            $defaultPort=':443';
        }
        else
        {
        	$http='http';
            $defaultPort=':80';
        }

        $port=':' . $_SERVER['SERVER_PORT'];
        if ($port===$defaultPort || $port==='') $port='';

        $host=Utils::get($_SERVER['HTTP_X_FORWARDED_HOST'],$_SERVER['SERVER_NAME']);

        return $http . '://' . $host . $port;
    }


    // r�pare $_GET, $_REQUEST et $_POST
    // remarque : php://input n'est pas disponible avec enctype="multipart/form-data".
    /**
     * Restaure la valeur correcte des param�tres de la requ�te pour lesquels
     * plusieurs valeurs ont �t� transmises.
     *
     * Par d�faut, en PHP, si on veut plusieurs valeurs pour un m�me param�tre,
     * il faut que ce param�tre soit nomm� comme un tableau php (i.e. dans un
     * formulaire, il faudra indiquer <input name="param[]"... />). Si on ne le
     * fait pas, seule la derniere valeur sera disponible dans PHP (pour
     * reprendre l'exemple ci-dessus, si on a deux champs input appell�s param,
     * sans les crochets, $_GET['param'] contiendra un scalaire correspondant
     * � ce qui a �t� saisi dans le second champ input).
     * cf http://php.net/language.variables.external.php
     *
     * Le but de cette m�thode est de modifier ce comportement de php et de
     * faire en sorte que, si plusieurs valeurs ont �t� transmises pour un
     * param�tre, on r�cup�re dans $_GET, $_POST et $_REQUEST non pas la
     * derni�re valeur transmise mais un tableau de param�tre.
     */
    public static function repairGetPostRequest()
    {
        // En m�thode 'GET', on travaille avec la query_string et $_GET
        if (self::isGet())
        {
            $raw = '&'. Runtime::$queryString=$_SERVER['QUERY_STRING'];
            $t= & $_GET;
        }

        // En m�thodes POST et PUT, on utilise l'entr�e standard et $_POST
        else
        {
            $raw = '&'. Runtime::$queryString=file_get_contents('php://input');
            $t = & $_POST;
        }

        // Parcourt tous les arguments et modifie ceux qui sont multivalu�s
        foreach($t as $key=>$value)
        {
            $re='/&'.preg_quote(urlencode($key),'~').'=([^&]*)/';
            if (preg_match_all($re,$raw, $matches, PREG_PATTERN_ORDER) > 1)
            {
                $_REQUEST[$key]=$t[$key]=array_map('urldecode', $matches[1]);
            }
        }
    }


    /**
     * Retourne vrai si on a �t� appell� en m�thode 'GET' ou 'HEAD'
     *
     * @return bool
     */
    public static function isGet()
    {
        return (strpos('GET HEAD', $_SERVER['REQUEST_METHOD']) !== false);
    }


    /**
     * Retourne vrai si on a �t� appell� en m�thode 'POST' ou 'PUT'
     *
     * @return bool
     */
    public static function isPost()
    {
        return (strpos('POST PUT', $_SERVER['REQUEST_METHOD']) !== false);
    }

    /**
     * D�termine si la requ�te en cours est une requ�te ajax ou non.
     *
     * La d�tection est bas�e sur la pr�sence ou non de l'ent�te http
     * X_REQUESTED_WITH qui est ajout� � la requ�te http par les librairies
     * ajax les plus courante (cas de prototype, jquery, YUI, mais pas
     * de dojo).
     *
     * @return boolean true si la requ�te http contient un ent�te
     * x-requested-withcontenant la valeur XMLHttpRequest (sensible � la casse)
     */
    public static function isAjax()
    {
    	return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH']==='XMLHttpRequest');
    }

//    /**
//     * Charge un fichier de configuration au format YAML
//     *
//     * Par d�faut, la fonction utilise la classe {@link spyc} mais si
//     * l'extension syck, qui est beaucoup plus rapide, est install�e, c'est
//     * cette extension qui sera utilis�e.
//     *
//     * @param string $path le path du fichier � charger
//     *
//     * @return array un tableau associatif contenant la configuration lue
//     */
//    public static function loadYaml($path)
//    {
//        // utilise l'extension syck.dll si elle est disponible
//        if (function_exists('syck_load'))
//            return syck_load($path);
//
//        // utilise la classe spyc sinon
//        require_once (Runtime::$fabRoot.'lib/Spyc/spyc.php5');
//        $spyc = new Spyc();
//        return $spyc->load($path);
//    }
//
//    public static function saveYaml($array, $path)
//    {
//        // utilise l'extension syck.dll si elle est disponible
////        if (function_exists('syck_load'))
////            return syck_load($path);
//
//        // utilise la classe spyc sinon
//        require_once (Runtime::$fabRoot.'lib/Spyc/spyc.php5');
//        $spyc = new Spyc;
//        file_put_contents($path, $spyc->dump($array, 2, 0));
//    }
//
    /**
     * Retourne le path du r�pertoire 'temp' du syst�me.
     * Le path obtenu n'a jamais de slash final.
     *
     * @return string le path obtenu
     */
    public static function getTempDirectory()
    {
        static $dir=null;

        // Si on a d�j� d�termin� le r�pertoire temp, termin�
        if (!is_null($dir)) return $dir;

        /*
        remarques : la fonction sys_get_temp_dir() a �t� introduite (en douce!)
        dans php 5.2.2 mais elle est bugg�e : elle ne tient pas compte des variables
        d'environnemenet temp ou tmp �ventuellement d�finies.
        Par ailleurs, ces variables ne semble plus transmises � php, donc on n'a
        aucun moyen de les r�cup�rer.
        R�sultat : pour le moment, un appel � Utils::getTempDirectory() retourne
        toujours le r�pertoire windows.
        */

        // Si la fonction sys_get_temp_dir est dispo (php 6 ?), on l'utilise
//        if (function_exists('sys_get_temp_dir') )
//            return rtrim(sys_get_temp_dir(), '/\\');

        // Regarde si on a l'une des variables d'environnement connues
        foreach(array('TMPDIR','TMP','TEMP') as $var)
            if ($h=Utils::get($_ENV[$var]))
                return rtrim($h, '/\\');

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
            return $dir='c:\\temp';
        else
            return $dir='/tmp';
    }

    /**
     * Cr�e un fichier temporaire et retourne un handle ouvert en �criture
     *
     * Le fichier est cr�� dans le r�pertoire temporaire du syst�me tel que
     * retourn� par {@link getTempDirectory()}.
     *
     * @param int $timeToLive la dur�e de vie du fichier temporaire, en secondes
     * Le fichier existera pendant au moins la dur�e indiqu�e, mais pourra �tre supprim�
     * � tout moment une fois ce d�lai d�pass�. Si timeToLive est � z�ro, le fichier sera
     * supprim� d�s la fin de l'ex�cution du script.
     *
     * @param string $basename le nom de base du fichier � cr�er
     *
     * @return resource le handle du fichier temporaire, ouvert en acc�s x+
     */
    public static function getTempFile($timeToLive=0, $basename='tmp.txt', $type='')
    {
        static $firstCall=true;

        // Si c'est le premier appel, installe le gestionnaire charg� du nettoyage
        if ($firstCall)
        {
        	$firstCall=false;
            register_shutdown_function(array(__CLASS__, 'cleanTempFiles'));
        }

        if ($timeToLive<0) $timeToLive=0;

        // D�termine le r�pertoire des fichiers temporaires
    	$dir=self::getTempDirectory().DIRECTORY_SEPARATOR;

        // Extrait de basename l'extension et le pr�fixe demand�s
        $ext=Utils::getExtension($basename);
        $basename='fab'.basename($basename, $ext);

        // Essaie de cr�er un fichier temporaire
        for($i=0; $i<100; $i++)
        {
            // Le nom du fichier g�n�r� contient :
            $path=sprintf
            (
                '%s%s-%d-%d-%d%s',
                $dir,
                $basename,      // le nom de base demand�
                time(),         // la date/heure de cr�ation
                rand(),         // un nombre al�atoire
                $timeToLive,    // la dur�e de vie du fichier
                $ext            // l'extension demand�e
            );

            // Essaie de cr�er le fichier, s'il n'existe pas on r�-essaiera avec un autre nombre al�atoire
            if (false !== $file=@fopen($path, 'xb+'))
            {
                if ($timeToLive===0)
                	register_shutdown_function('unlink', $path);
                return $file;
            }
        }

        throw new Exception('Impossible de cr�er le fichier temporaire');
    }

    /**
     * Supprime les fichiers temporaires cr��s par getTempFile
     *
     * Cette fonction est automatiquement install�e comme fonction de terminaison
     * ex�cut�e � la fin du script lorsque getTempFile() est appell�e, mais elle peut
     * aussi �tre appell�e directement.
     */
    public static function cleanTempFiles() // private impossible, register_shutdown_function veut une fonction publique
    {
        $dir=self::getTempDirectory().DIRECTORY_SEPARATOR;
        foreach(glob($dir. '*-*-*-*', GLOB_NOSORT|GLOB_NOESCAPE) as $path)
        {
            if (preg_match('~.*-(\d+)-\d+-(\d+).*~',basename($path), $match))
            {
                $creation=(int)$match[1];
                $ttl=(int)$match[2];

                 // on ne supprime pas les fichiers qui ont un ttl=0 car sinon, on risque
                 // de supprimer un fichier qui vient juste d'�tre cr�� par un autre script
                 // (ils seront supprim�s � la fin du script, cf getTempFile)
                if ($ttl!==0)
                    if (time()>$creation+$ttl) @unlink($path);
            }
        }
    }

    /**
     * Retourne l'adresse d'un fichier ouvert (son chemin d'acc�s pour un fichier local)
     *
     * Cette fonction a �t� �crite � l'origine pour conna�tre le path d'un fichier
     * temporaire retourn� par getTempFile(), c'est simplement un wrapper autour de la
     * fonction stream_get_meta_data().
     *
     * @param resource $handle le handle du fichier ouvert
     * @return string
     */
    public static function getFileUri($handle)
    {
    	$data=stream_get_meta_data($handle);
        return $data['uri'];
    }

    /**
     * Affiche ou retourne la repr�sentation sous forme de code php
     * du contenu de la variable pass�e en param�tre.
     *
     * Cette fonction fait la m�me chose que la fonction standard
     * {@link http://php.net/var_export var_export()} de php, mais elle
     * g�n�re un code plus compact pour les tableaux (pour les autres variables,
     * la sortie g�n�r�e est la m�me qu'avec var_export())
     *
     * - pas de retours chariots ni d'espaces inutiles dans le code g�n�r�
     * - ne g�n�re les index de tableau que s'ils sont diff�rents de
     * l'index qui serait automatiquement attribu� s'il n'avait pas �t�
     * sp�cifi�
     *
     * Exemple :
     * avec le tableau
     * <code>$t=array('a', 10=>'b', 'c', 'key'=>'d', 'e');</code>
     *
     * On g�n�re le code :
     * <code>array('a',10=>'b','c','key'=>'d','e')</code>
     *
     * Alors que la fonction var_export de php g�n�re :
     * <code>
     * array (
     *   0 => 'a',
     *   10 => 'b',
     *   11 => 'c',
     *   'key' => 'd',
     *   12 => 'e',
     * )
     * </code>
     *
     * @param mixed $var la variable � afficher
     * @param boolean $return false : la fonction affiche le r�sultat,
     * true : la fonction retourne le r�sultat
     */
    public static function varExport($var, $return = false)
    {
        if(is_null($var)) return 'null'; // juste parce que je le pr�f�re en minu...
        if (! is_array($var)) return var_export($var, $return);

        $t = array();
        $index=0;
        foreach ($var as $key => $value)
        {
            if ($key!==$index)
            {
                $t[] = var_export($key, true).'=>'.self::varExport($value, true);
                if (is_int($key)) $index=$key+1;
            }
            else
            {
                $t[] = self::varExport($value, true);
                $index++;
            }
        }
        $code = 'array('.implode(',', $t).')';
        if ($return) return $code;
        echo $code;
    }

    /**
     * Retourne une version coloris�e du code php pass� en param�tre
     *
     * Il s'agit d'un wrapper autour de la fonction php highlight_string()
     * qui se charge d'ajouter (puis d'enlever) les tags de d�but et de fin
     * de code php
     *
     * @param string $php le code php � coloriser
     * @return string
     */
    public static function highlight($php)
    {
        return str_replace(array('&lt;?php&nbsp;', '?&gt;'), '', highlight_string('<?php '.$php.'?>', true));

    }

    /**
     * Retourne le num�ro du dernier jour d'un mois (o�, ce qui est la m�me
     * chose, le nombre de jours d'un mois donn�).
     *
     * La fonction tiens compte des ann�es bissextiles pour le mois de f�vrier.
     *
     * @param int $month le num�ro du mois
     * @param int $year l'ann�e.
     */
    public static function lastDay($month, $year=null)
    {
        static $last=array(0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);

        if ($month !== 2) return $last[$month];
        if (is_null($year))
        {
        	$year=getdate();
            $year=$year['year'];
        }
        return 28 + ($year % 4 === 0 ? 1 : 0); // good until 2100
    }

    /**
     * G�n�re les termes utilisables dans une �quation de recherche bool�enne
     * permettant de rechercher les enregistrements ayant une date situ�e entre la
     * date de d�but et de fin donn�es.
     *
     * Les jours sont repr�sent�s par un terme de la forme 'yyyymmdd', les mois
     * entiers par un terme avec troncature de la forme 'yyyymm*' et les ann�es enti�res
     * par un terme avec troncature de la forme 'yyyy*'.
     *
     * L'algorithme utilis� essaie de limiter autant que possible le nombre de termes
     * g�n�r�s. Il g�re correctement le mois de f�vrier, y compris en cas d'ann�e
     * bissextile.
     *
     * Exemple : dateRange('20070101','20080302') va retourner un tableau contenant
     * les termes '2007*', '200801*', '200802*', '20080301' et '20080302'.
     *
     * Les dates doivent �tre indiqu�es au format GNU :
     * {@link http://www.gnu.org/software/tar/manual/html_node/tar_109.html}
     *
     * Remarques :
     *
     * - les bornes de l'intervalles sont inclues dans l'intervalle.
     *
     * - si vous utilisez des unit�s GNU relatives, telles que tomorrow ou +1day,
     * il faut que la premi�re date indiqu�e ($fromDate) fixe le temps de r�f�rence.
     * Si la premi�re date est elle-m�me une unit� relative, alors elle sera calcul�e
     * par rapport � la date actuelle.
     * Autrement dit, dateRange('today','+1week') fonctionnera mais pas
     * dateRange('+1week','today'). Dans le deuxi�me cas, +1week sera calcul� � partir
     * de maintenant, puis 'today' sera calcul� par rapport � cette date, ce qui fait
     * que vous obtiendrez un intervalle d'un seul jour (le jour dans une semaine).
     *
     * - l'algorithme ne pousse pas jusqu'au bout les optimisations permettant de limiter
     * le nombre de termes g�n�r�s. Par exemple, si on a les 11 premiers mois de l'ann�e,
     * il g�n�re tous les mois un par un et non pas '20070*', '200710'et '200711' (idem
     * pour les jours et les ann�es.
     *
     * @param $fromDate la date de d�part de l'intervalle.
     *
     * @param $toDate la date de fin de l'intervalle.
     *
     * @return array un tableau contenant tous les tokens de recherche correspondant
     * � l'intervalle indiqu�. Pour cr�er une �quation de recherche � partir du tableau
     * obtenu, vous pouvez utiliser quelque chose comme :
     *
     * <code>
     *      $result=Utils::dateRange('20070101','20080302');
     *      $equation='creation='.implode(' OR creation=', $result);
     * </code>
     */
    public static function dateRange($fromDate, $toDate, $wildcard=false)
    {
        $result=array();
        $wild = $wildcard ? '*' : '';

        // Convertit les dates au format GNU en timestamps

        if (false === $fromDate=strtotime($fromDate))
            throw new Exception('Date de d�but invalide : '.$fromDate);

        if (false === $toDate=strtotime($toDate))
            throw new Exception('Date de fin invalide : '.$toDate);

        // Inverse les dates si la date de d�but est apr�s la date de fin
        if ($fromDate>$toDate)
          list($fromDate,$toDate)=array($toDate,$fromDate);

        // Extrait l'ann�e, le mois et le jour de chaque date
        $t=getdate($fromDate);
        $y1=$t['year'];
        $m1=$t['mon'];
        $d1=$t['mday'];

        $t=getdate($toDate);
        $y2=$t['year'];
        $m2=$t['mon'];
        $d2=$t['mday'];

        /*
            Algorithme :
            - y1=y2
                - m1=m2
                    * jours de y1/m1/d1 � y1/m1/d2 ou y1/m1* si d1=1 et d2=lastday(m1,y1)
                - m1<m2
                    - ann�e compl�te (d1=1 et m1=1 et m2=12 et d2=12):
                        * y1*
                    - sinon
                        * pour m1 : jours de y1/m1/d1 � y1/m1/lastDay(m1) ou y1/m1* si d1=1
                        * mois de y1/m1+1 � y1/m2-1
                        * pour m2 : jours de y1/m2/01 � y1/m2/d2 ou y1/m2* si d2=lastDay(m2,y1)
            - y1<y2
                - y1 ann�e compl�te (d1=1 et m1=1)
                    * y1*
                - sinon
                    * pour m1 : jours de y1/m1/d1 � y1/m1/lastDay(m1) ou y1/m1* si d1=1
                    * pour y1 : mois de y1/m1+1 � y1/12
                - ann�es de y1+1 � y2-1
                - y2 ann�e compl�te (m2=12 et d2=31)
                    * y2*
                -sinon
                    * pour y2 : mois de y2/01 � y2/m2-1
                    * pour m2 : jours de y2/m2/01 � y2/m2/d2 ou y2/m2* si d2=lastDay(m2)
        */
        if ($y1==$y2)
        {
        	if ($m1==$m2)
            {
                // jours de y1/m1/d1 � y1/m1/d2 ou y1/m1* si d1=1 et d2=lastday(m1,y1)
                if ($d1==1 && $d2==Utils::lastDay($m1,$y1))
                    $result[]=sprintf('%d%02d*', $y1, $m1);
                else
                    for ( ; $d1 <= $d2 ; $d1++)
                        $result[]=sprintf('%d%02d%02d'.$wild, $y1, $m1, $d1);
            }
            else // m1<m2
            {
                // ann�e compl�te
                if ($d1==1 && $m1==1 && $m2==12 && $d2==31)
            	   $result[]=sprintf('%d*', $y1);
                else
                {

                    // pour m1 : jours de y1/m1/d1 � y1/m1/lastDay(m1) ou y1/m1* si d1=1
                    if ($d1==1)
                        $result[]=sprintf('%d%02d*', $y1, $m1);
                    else
                        for ($last=Utils::lastDay($m1, $y1) ; $d1 <= $last ; $d1++)
                            $result[]=sprintf('%d%02d%02d'.$wild, $y1, $m1, $d1);

                    // mois de y1/m1+1 � y1/m2-1
                    while (++$m1 < $m2)
                        $result[]=sprintf('%d%02d*', $y1, $m1);

                    // pour m2 : jours de y1/m2/01 � y1/m2/d2 ou y1/m2* si d2=lastDay(m2,y1)
                    if ($d2==Utils::lastDay($m2,$y1))
                        $result[]=sprintf('%d%02d*', $y1, $m2);
                    else
                        for ($d1=1 ; $d1 <= $d2 ; $d1++)
                            $result[]=sprintf('%d%02d%02d'.$wild, $y1, $m2, $d1);
                }
            }
        }
        else // y1<y2
        {
            // y1 ann�e compl�te
            if ($d1==1 && $m1==1)
               $result[]=sprintf('%d*', $y1);
            else
            {
                // pour m1 : jours de y1/m1/d1 � y1/m1/lastDay(m1) ou y1/m1* si d1=1
                if ($d1==1)
                    $result[]=sprintf('%d%02d*', $y1, $m1);
                else
                    for ($last = Utils::lastDay($m1, $y1) ; $d1 <= $last ; $d1++)
                        $result[]=sprintf('%d%02d%02d'.$wild, $y1, $m1, $d1);

                // pour y1 : mois de y1/m1+1 � y1/12
                while (++$m1 <= 12)
                    $result[]=sprintf('%d%02d*', $y1, $m1);
            }

            // ann�es de y1+1 � y2-1
            while (++$y1 < $y2)
                $result[]=sprintf('%d*', $y1);

            // y2 ann�e compl�te
            if ($m2==12 && $d2==31)
               $result[]=sprintf('%d*', $y2);
            else
            {
                // pour y2 : mois de y2/01 � y2/m2-1
                for ($m1=1; $m1<$m2; $m1++)
                    $result[]=sprintf('%d%02d*', $y1, $m1);

                // pour m2 : jours de y2/m2/01 � y2/m2/d2 ou y2/m2* si d2=lastDay(m2)
                if ($d2==Utils::lastDay($m2,$y2))
                    $result[]=sprintf('%d%02d*', $y2, $m2);
                else
                    for ($d1=1 ; $d1 <= $d2 ; $d1++)
                        $result[]=sprintf('%d%02d%02d'.$wild, $y1, $m1, $d1);
            }
        }
        return $result;
    }

    /**
     * Formatte une chaine pour qu'elle puisse �tre �crite dans un fichier CSV.
     *
     * Extrait de la {@link http://www.rfc-editor.org/rfc/rfc4180.txt RFC 4180} :
     *
     * Fields containing line breaks (CRLF), double quotes, and commas should be
     * enclosed in double-quotes.
     *
     * If double-quotes are used to enclose fields, then a double-quote appearing
     * inside a field must be escaped by preceding it with another double quote.
     */
    public static function csvQuote($string, $sep="\t")
    {
//        // Si on nous a pass� un tableau (un champ articles par exemple), convertit en chaine
//        if (is_array($string)) $string=implode($sep,$string);

        // Si la chaine ne contient aucun caract�re sp�cial, on la retourne telle quelle
        if (strpbrk($string, "\n\r\"".$sep) === false)
            return $string;

        // Encadre la chaine de guillemets et doubles guillemets existants
        return '"' . str_replace('"', '""', $string) . '"';
    }

    private static $captureFile=null;

    /**
     * Handler utilis� par {@link startCapture()} et {@link endCapture()}
     */
    public static function captureHandler($data, $mode)
    {
        /*
         * Important : la m�thode *DOIT* �tre publique
         * Si on appelle ob_start() avec un handler priv�, on n'a aucun message d'erreur
         * et ob_start() retourne true, mais PHP plante (et apache aussi).
         * Il faut donc que la m�thode soit publique ou bien utiliser une fonction globale
         * comme handler
         */
//        $len=strlen($data);
//        fwrite(self::$captureFile, "\nAppel du handler. mode=$mode, len=$len\n");
        fwrite(self::$captureFile, $data);
        fflush(self::$captureFile); // sur quelques tests, semble plus rapide de flusher � chaque appel, �tonnant...
        return '';
    }

    /**
     * D�marre la redirection de la sortie standard vers un fichier
     *
     * A partir du moment o� startCapture() est appell�e, toutes les sorties g�n�r�es
     * depuis echo, print, readfile, etc. sont redirig�es vers un fichier.
     *
     * La redirection prend fin lorsque {@link endCapture()} est appell�e.
     *
     * Une seule capture peut �tre active � la fois. Si on appelle startCapture() alors
     * qu'une capture a d�j� �t� lanc�e, une exception sera g�n�r�e.
     *
     * Par d�faut, la capture se fait vers un fichier temporaire cr�� par
     * {@link getTempFile()}.
     *
     * Exemple :
     * <code>
     * Utils::startCapture();
     * </code>
     *
     * Il est possible d'indiquer la dur�e de vie du fichier temporaire (ttl)
     * en param�tre (en secondes).
     *
     * Exemple :
     * <code>
     * Utils::startCapture(3600); // cr�e un fichier temporaire valable pour une heure
     * </code>
     *
     * Si vous souhaitez faire une capture vers un fichier p�renne (non temporaire), vous
     * pouvez indiquer le path complet du fichier de capture.
     *
     * Exemple :
     * <code>
     *     Utils::startCapture(dirname(__FILE__).'/static.html');
     * </code>
     *
     * @param mixed $path_or_ttl path du fichier de capture ou dur�e de vie du fichier temporaire
     * @param int $chunkSize taille des blocs de capture (pass� lors de l'appel � ob_start())
     * @return string le path du fichier de capture
     */
    public static function startCapture($path_or_ttl=0, $chunkSize=8192)
    {
        // Erreur si une capture est d�j� en cours
        if (!is_null(self::$captureFile))
            throw new Exception('Une capture est d�j� en cours');

        // Si l'utilisateur nous a pass� le path du fichier � cr�er, on l'ouvre
        if (is_string($path_or_ttl))
        {
            if (!is_resource(self::$captureFile=@fopen($path_or_ttl, 'wb+')))
                throw new Exception("Impossible de cr�er le fichier $path_or_ttl");
        }

        // Entier ou null
        else
        {
            if (! is_int($path_or_ttl))
                throw new Exception('Param�tre incorrect, entier ou chaine attendue');
            self::$captureFile=self::getTempFile($path_or_ttl);
        }

        // Installe le gestionnaire
        if (!ob_start(array(__CLASS__,'captureHandler'), $chunkSize)) // le handler DOIT �tre une m�thode publique (cf commentaire dans captureHandler)
        {
            fclose(self::$captureFile);
            self::$captureFile=null;
            throw new Exception("Impossible d'installer le gestionnaire de capture");
        }

        // Retourne le path du fichier de capture
        return Utils::getFileUri(self::$captureFile);
    }

    /**
     * Met fin � une capture commenc�e par un appel � {@link startCapture()}
     *
     * G�n�re une exception si aucune captre n'est en cours
     *
     * @return string le path du fichier de capture
     */
    public static function endCapture()
    {
        // Erreur s'il n'y a aucune capture en cours
        if (is_null(self::$captureFile))
            throw new Exception('Aucune capture en cours');

        // Flushe les donn�es �ventuelles en attente
        ob_end_flush();

        // Ferme le fichier de capture
        $path=Utils::getFileUri(self::$captureFile);
        fclose(self::$captureFile);
        self::$captureFile=null;

        // Retourne le path du fichier de capture
        return $path;
    }

    /**
     * Applique utf8_encode r�cursivement sur une variable.
     *
     * Les chaines de caract�res sont converties en utilisant utf8_encode.
     * Les autres types simples (entiers, bool�ens...) sont retourn�s tels
     * quels.
     *
     * Pour les tableaux, la fonction encode � la fois les valeur et les cl�s
     * si celles-ci sont des chaines.
     * Les tableaux de tableaux sont g�r�s et encod�s r�cursivement.
     *
     * @param mixed $var le tableau � convertir
     * @return mixed
     */
    public static function utf8Encode($var)
    {
        // Chaine : on utilise directement utf8_encode
        if (is_string($var)) return utf8_encode($var);

        // Autre type simple : retourne tel quel
        if (is_scalar($var) || is_null($var)) return $var;

        // Tableau ou objet
        $t = array();
        foreach ($var as $key => $value)
        {
            if (is_int($key))
                $t[$key] = self::utf8Encode($value);
            else
                $t[utf8_encode($key)] = self::utf8Encode($value);
        }
        return is_array($var) ? $t : (object) $t;
    }

    /**
     * Applique utf8_decode r�cursivement sur une variable.
     *
     * Les chaines de caract�res sont converties en utilisant utf8_decode.
     * Les autres types simples (entiers, bool�ens...) sont retourn�s tels
     * quels.
     *
     * Pour les tableaux, la fonction d�code � la fois les valeur et les cl�s
     * si celles-ci sont des chaines.
     * Les tableaux de tableaux sont g�r�s et d�cod�s r�cursivement.
     *
     * @param mixed $var le tableau � convertir
     * @return mixed
     */
    public static function utf8Decode($var)
    {
        // Chaine : on utilise directement utf8_decode
        if (is_string($var)) return utf8_decode($var);

        // Autre type simple : retourne tel quel
        if (is_scalar($var) || is_null($var)) return $var;

        // Tableau ou objet
        $t = array();
        foreach ($var as $key => $value)
        {
            if (is_int($key))
                $t[$key] = self::utf8Decode($value);
            else
                $t[utf8_decode($key)] = self::utf8Decode($value);
        }
        return is_array($var) ? $t : (object) $t;
    }

    /**
     * Formatte la taille d'un fichier ou d'un dossier pour un affichage �
     * l'utilisateur.
     *
     * La fonction arrondi la taille � l'unit� la plus proche et retourne une
     * chaine contenant la valeur arrondie suivie d'un espace et de l'unit�
     * (par exemple '199 Mo', '3.89 Mo', etc.)
     *
     * @param int $bytes
     * @return string
     */
    public static function formatSize($bytes)
    {
        static $symbols = array('octets', 'Ko', 'Mo', 'Go', 'To', 'Po', 'Eo', 'Zo', 'Yo');

        if (0 === $bytes=(int)$bytes) return '0';
        $exp = floor(log($bytes)/log(1024));

        return round($bytes/pow(1024, floor($exp)),2) . ' ' . $symbols[$exp];
    }

    public static function friendlyDate($timestamp, $today='%Hh%M', $yesterday='hier � %Hh%M', $thisYear='%d/%m � %Hh%M', $other='%d/%m/%y � %Hh%M')
    {
        // am�liorations : pour comparer les dates, on utilise � chaque fois
        // date(fmt, $x)===date($fmt)
        // c'est un peu lourd.
        // a priori, on peut faire la m�me chose avec un simple modulo :
        // if ($creation % 86400 === time() % 86400) -> aujourd'hui
        // if ($creation % 86400 === (time() % 86400) - 1) -> hier
        // � tester
        if(is_null($timestamp)) return '-';
        if ($timestamp===0) return 'd�s que possible';

        // aujourd'hui
        if (date('Ymd', $timestamp)===date('Ymd'))
            return strftime($today, $timestamp);

        // hier
        if (date('Ymd', $timestamp)===date('Ymd', time()-86400))
            return strftime($yesterday, $timestamp);

        // m�me ann�e
        if (date('Y', $timestamp)===date('Y'))
            return strftime($thisYear, $timestamp);

        // autre
        return strftime($other, $timestamp);
    }

    /**
     * Retourne la dur�e �coul�e pass�e en param�tre sous forme "humaine"
     * (Par exemple 1 jour 2 heures 20 minutes et 5 secondes)
     *
     * @param int|float $time dur�e �coul�e en secondes.
     * @return string
     */
    public static function friendlyElapsedTime($time)
    {
        /*
         * Remarque DM+SF, 11/12/09 :
         * Les floats de php ne sont pas simples � manipuler...
         *
         * Dans la version pr�c�dente, une m�me valeur (par exemple 4620) ne
         * donnait pas toujours le m�me r�sultat (parfois le bon : "1 h 17 min"
         * et parfois "1 h 16,: min").
         *
         * Nous n'avons pas r�ussi � comprendre le probl�me (bug php), mais le
         * fait de caster les floats en int r�soud le probl�me.
         *
         * => ne pas enlever les (int) qui figurent devant les appels � floor()
         * et round()
         */
//        if ($time<1) return ((int) round($time*1000)) . ' ms';
        if ($time<1) return round($time,2) . ' secondes';

        $h='';
        if (is_float($time) && $time>60) $time=(int) round($time);

        $days = (int) floor($time/60/60/24);
        $time -= $days*60*60*24;
        if ($days) $h.= $days . ' jour' . ($days>1 ? 's' : '') . ' ';

        $hours = (int) floor($time/60/60);
        $time -= $hours*60*60;
        if ($days || $hours) $h.= $hours . ' heure' . ($hours>1 ? 's' : '') . ' ';

        $mins = (int) floor($time/60);
        $time -= $mins*60;
        if ($days || $hours || $mins) $h.= $mins . ' minute' . ($mins>1 ? 's' : '') . ' ';

        $secs = (int)round($time,2);
        if ($h==='' || $secs>0)
            $h.= ($h ? 'et ' : '') . $secs . ' seconde' . ($secs >1 ? 's' : '');

        return $h;
    }


    /**
     * Retourne la taille sur le disque d'un r�pertoire
     *
     * @param string $path
     * @return int
     */
    public static function dirSize($path)
    {
        if (is_file($path)) return filesize($path);
        if (!is_dir($path)) return 0;
        $size = 0;

        foreach(scandir($path) as $item)
        {
            if (is_dir($item))
            {
                if ($item==='.' || $item==='..') continue;
                $size += self::dirSize($path . '/' . $item);
            }
            else
            {
                $size += filesize($path . '/' . $item);
            }
        }
        return $size;
    }

    public static function ksort(array $array)
    {
        ksort($array);
        return $array;
    }

    /**
     * Convertit les urls et les adresses e-mails pr�sentes dans le texte
     * pass� en param�tre en lien cliquable.
     *
     * La fonction transforme toutes les chaines xxx qui ressemblent � une url
     * ou � une adresse e-mail en lien html de la forme :
     * <code>&lt;a href="xxx">xxx&lt;/a></code>
     *
     * La fonction prend garde de ne pas rajouter un tag 'a' aux liens qui
     * sont d�j� englob�s dans un tag 'a'.
     *
     * Les adresses IP sont �galement reconnues (pour les urls, pas pour les
     * e-mails).
     *
     * @param string $text le texte a transform�
     * @return string le texte obtenu
     */
    public static function autoLink($text)
    {
        $IPByte    = '\d{1,3}';                             // Un octet dans une adresse IP
        $IP    = "$IPByte\.$IPByte\.$IPByte\.$IPByte";      // Adresse IP (IPV4)
        $Protocol   = '(?:http|https|ftp)://';              // protocole internet
        $TopDomain  = '\.[A-Za-z]{2,4}';                    // un point suivi d'un code pays de 2 ou 3 ou 4 lettres
        $Ident      = '[\w-]+';                             // un 'mot' du nom de domaine
        $Domain     = "$Ident(?:\.$Ident)*$TopDomain";      // nom de domaine
        $Port       = '\:\d+';                              // port TCP
        $UrlPath    = '(?:/[^#) /\\n\\r<]+)+';               // path d'un document (y compris �ventuelle query string)
        $Bookmark   = '#\w+';                               // ancre au sein du document (=id hml valide)
        $DomainIP  = "$Domain|$IP";                         // Une nom de domaine ou une adresse IP
        $Url        = "($Protocol|www\.|ftp\.)(?:$DomainIP)(?:$Port)?(?:$UrlPath)?/?(?:$Bookmark)?";    // url compl�te
        $Email      = "$Ident(?:\.$Ident)*@$Domain";         // Adresse e-mail
        $lead       = '(?:<\w+.*?>)?';                      // utilis� pour tester si l'url est d�j� dans un <a>...</a>

        $text=preg_replace_callback("~($lead)($Url)~", array('Utils','autolinkCallbackForUrls'), $text);
        $text=preg_replace_callback("~($lead)($Email)~", array('Utils','autolinkCallbackForEmails'), $text);
        return $text;
    }

    /**
     * Callback utilis� par {@link autoLink()} pour reconna�tre les urls.
     *
     * @param array $matches les occurences trouv�es par preg_replace_callback()
     * @return string le texte obtenu
     */
    private static function autolinkCallbackForUrls(array $matches)
    {
        // 1=lead, 2=url, 3=protocole ou 'www.' ou 'ftp.'
        if (strpos($matches[1],'<a ')===0) // examine ce qui pr�c�de, si c'est '<a xxx' retourne inchang�
            return $matches[0];

        $url=$matches[2];
        if(strpos($matches[3],'://')===false)
        {
            if (stripos($matches[3], 'ftp')===0)
                $url='ftp://'.$url;
            else
                $url='http://'.$url;
        }
        return $matches[1].'<a href="' . $url . '">' . $matches[2] . '</a>';
    }

    /**
     * Callback utilis� par {@link autoLink()} pour reconna�tre les adresses
     * e-mails.
     *
     * @param array $matches les occurences trouv�es par preg_replace_callback()
     * @return string le texte obtenu
     */
    private static function autolinkCallbackForEmails(array $matches)
    {
        // 1=lead, 2=email
        if (strpos($matches[1],'<a ')===0) // examine ce qui pr�c�de, si c'est '<a xxx' retourne inchang�
            return $matches[0];

        return $matches[1].'<a href="mailto:' . $matches[2] . '">' . $matches[2] . '</a>';
    }

    /**
     * Construit la liste des tokens pour un texte donn�.
     *
     * @param string $text
     * @return array
     */
    public static function tokenize($text, $mode=1)
    {
        static $charFroms = '\'-ABCDEFGHIJKLMNOPQRSTUVWXYZ���������������������������������������������������������������';
        static $charTo    =  '  abcdefghijklmnopqrstuvwxyz��aaaaaa�ceeeeiiiidnooooo�uuuuytsaaaaaa�ceeeeiiiidnooooouuuuyty';

        // Caract�res sp�ciaux :
        // � = "O barr�", utilis� en danois, f�ringien et norv�gien, translitt�ration : "oe"
        // � et � = "thorn" (maju et minu). Translitt�ration standard : "th", remplac� par "t" dans notre table.
        // � et � = "eth" (maju et minu). Translitt�ration : "D"
        // Sources : wikipedia + http://www.fao.org/DOCREP/003/Q0929F/q0929f04.htm

        // Convertit les sigles en mots
        $text=preg_replace_callback('~(?:[a-z0-9]\.){2,9}~i', array(__CLASS__, 'acronymToTerm'), $text);

        // Convertit les caract�res
        $text=strtr($text, $charFroms, $charTo);

        // G�re les lettres doubles
        $text=strtr($text, array('�'=>'ae', '�'=>'oe'));

        // Retourne un tableau contenant tous les mots pr�sents
        return str_word_count($text, $mode, '0123456789@_');
    }


    /**
     * Fonction utilitaire utilis�e par {@link tokenize()} pour convertir
     * les acronymes en mots
     *
     * @param array $matches
     * @return string
     */
    public static function acronymToTerm($matches)
    {
        return str_replace('.', '', $matches[0]);
    }

    /**
     * Retourne le type mime du fichier dont le nom est pass� en
     * param�tre en se basant sur l'extension.
     *
     * Retourne un type mime g�n�rique si l'extension est absente ou
     * n'est pas reconnue.
     *
     * @param string $path
     * @return string
     */
    public static function mimeType($path)
    {
        // types mimes autoris�s pour le site fab
        $mimes=array
        (
            '.htm'      => 'text/html',
            '.html'     => 'text/html',

            '.gif'      => 'image/gif',
            '.jpg'      => 'image/jpeg',
            '.png'      =>  'image/png',

            '.css'      =>  'text/css',

            '.js'       =>  'application/x-javascript',

            '.php'      =>  'text/php',
            '.txt'      =>  'text/plain',

            '.xml'      =>  'text/xml',
            '.config'   =>  'text/xml',
        );

        $extension=strtolower(Utils::getExtension($path));
        if (isset($mimes[$extension]))
            return $mimes[$extension];

        return 'application/octet-stream';
    }

    /**
     * Ajoute une cl� et une valeur dans un tableau.
     *
     * La fonction ajoute la cl� et la valeur indiqu�es � la fin du tableau.
     * Si la cl� existe d�j� dans le tableau, la valeur existante est convertie
     * en tableau et la valeur indiqu�e est ajout�e � la fin du tableau obtenu.
     *
     * Cette fonction est utile pour g�rer une liste de cl�s auxquelles peuvent
     * �tre associ�es une ou plusieurs valeurs. Le tableau obtenu contiendra
     * toutes les cl�s indiqu�es, dans l'ordre dans lequel elles ont �t�
     * rencontr�es pour la premi�re fois, et chaque cl� indiquera la ou les
     * valeurs associ�es. Pour chaque cl�, count(value) indique le nombre de
     * fois ou la cl� a �t� rencontr�e.
     *
     * Remarque : la valeur doit �tre de type scalaire ou objet, cela ne
     * marchera pas si vous passez un tableau.
     *
     * La fonction {@link arrayPrependKey()} est tr�s similaire mais effectue
     * les insertions en ordre inverse.
     *
     * @param array $array le tableau � modifier
     * @param scalar $key la cl� � ajouter au tableau
     * @param scalar|object $value la valeur associ�e � la cl�
     */
    public static function arrayAppendKey(array & $array, $key, $value)
    {
        // Si la cl� n'existe pas d�j�, on l'ins�re � la fin du tableau
        if (!array_key_exists($key, $array))
        {
            $array[$key]=$value;
            return;
        }

        // La cl� existe d�j�
        $item=& $array[$key];

        // Si c'est d�j� un tableau, ajoute la valeur � la fin du tableau
        if (is_array($item))
            $item[]=$value;

        // Sinon, cr�e un tableau contenant la valeur existante et la valeur indiqu�e
        else
            $item=array($item, $value);
    }

    /**
     * Ajoute une cl� et une valeur dans un tableau.
     *
     * La fonction ajoute la cl� et la valeur indiqu�es au d�but du tableau.
     * Si la cl� existe d�j� dans le tableau, la valeur existante est convertie
     * en tableau et la valeur indiqu�e est ajout�e au d�but du tableau obtenu.
     *
     * Cette fonction est utile pour g�rer une liste de cl�s auxquelles peuvent
     * �tre associ�es une ou plusieurs valeurs. Le tableau obtenu contiendra
     * toutes les cl�s indiqu�es, en ordre inverse de l'ordre dans lequel elles
     * ont �t� rencontr�es pour la premi�re fois, et chaque cl� indiquera la ou
     * les valeurs associ�es (en ordre inverse �galement).
     *
     * Remarque : la valeur doit �tre de type scalaire ou objet, cela ne
     * marchera pas si vous passez un tableau.
     *
     * La fonction {@link arrayAppendKey()} est tr�s similaire mais effectue
     * les insertions en ordre normal.
     *
     * @param array $array le tableau � modifier
     * @param scalar $key la cl� � ajouter au tableau
     * @param scalar|object $value la valeur associ�e � la cl�
     */
    public static function arrayPrependKey(array & $array, $key, $value)
    {
        // Si la cl� n'existe pas d�j�, on l'ins�re au d�but du tableau
        if (!array_key_exists($key, $array))
        {
            $array=array($key=>$value) + $array; // y-a-t-il un autre moyen ?
            return;
        }

        // La cl� existe d�j�
        $item=& $array[$key];

        // Si c'est d�j� un tableau, ajoute la valeur au d�but du tableau
        if (is_array($item))
            array_unshift($item, $value);

        // Sinon, cr�e un tableau contenant la valeur indiqu�e et la valeur existante
        else
            $item=array($value, $item);
    }

    /**
     * Convertit en octets une taille indiqu�e dans php.ini.
     *
     * @size string une taille en
     * {@link http://fr.php.net/manual/fr/faq.using.php#faq.using.shorthandbytes "notation st�nographique"}
     * ( 2M, 2K, 3G...)
     *
     * @return int la taille en octets.
     */
    private static function convertSize($size)
    {
        $size=trim($size);
        switch(strtolower(substr($size,-1)))
        {
            // Le modifieur 'G' est disponible depuis PHP 5.1.0
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }
        return $size;
    }

    /*
     * Retourne la taille maximale autoris� pour un fichier upload�.
     *
     * @return int la taille maximale en octets.
     */
    public static function uploadMaxSize()
    {
        $maxUpload=self::convertSize(ini_get('upload_max_filesize'));
        $maxPost=self::convertSize(ini_get('post_max_size'));
        $memoryLimit=self::convertSize(ini_get('memory_limit'));
        if ($memoryLimit===-1) $memoryLimit=PHP_INT_MAX;

        return min($maxUpload, $maxPost, $memoryLimit);
    }


    /**
     * V�rifie et stocke sur le serveur un fichier upload� par l'utilisateur.
     *
     * @param array $file un �l�ment du tableau
     * {@link http://php.net/features.file-upload $_FILES} d�crivant le fichier
     * upload� par l'utilisateur
     *
     * @param string $path le path final sur le serveur du fichier upload�. Si
     * le fichier upload� est valide (pas d'erreur, etc.) il sera d�plac� vers
     * ce path en utilisant la fonction
     * {@link http://php.net/move_uploaded_file move_uploaded_file()} de php.
     *
     * @param callback $callback m�thode � appeler pour v�rifier que le
     * fichier upload� est valide. Le callback sera appel� avec en param�tre le
     * path complet du fichier � valider. Elle doit retourner true si le fichier
     * est valide ou un message d'erreur dans le cas contraire :
     * <code>
     * public function callback(string $path) : true|string
     * </code>
     *
     * @return string|bool la fonction retourne :
     * - string : en cas d'erreur
     * - true : si le fichier est ok (il a �t� valid� et stock� dans $path)
     * - false : le tableau pass� en param�tre ne contenait aucun fichier
     * upload� (cela se produit si le formulaire a �t� valid� mais que le input
     * file �tait vide).
     */
    public static function uploadFile($file, $path, $callback=null)
    {
        switch($file['error'])
        {
            case UPLOAD_ERR_OK:
                if ($file['size']==0)
                    return sprintf("Le fichier '%s' est vide (taille=0)", $file['name']);

                if (! is_null($callback))
                {
                    $result=call_user_func($callback, $file['tmp_name'], $file['name']);
                    switch(true)
                    {
                        case $result===true: // ok
                            break;

                        case is_null($result): // ok aussi, le callback n'a pas signal� d'erreur
                            break;

                        case $result===false: // non attendu, cas d'erreur
                            $result='le callback a retourn� false';
                            // pas de break : voulu

                        default:    // erreur
                            return sprintf("Le fichier '%s' n'est pas valide : %s", $file['name'], $result);
                    }
                }

                if (move_uploaded_file($file['tmp_name'], $path)===false)
                    return sprintf("Impossible d'enregistrer le fichier '%s'.", $file['name']);

                // tout est ok
                return true;

            case UPLOAD_ERR_INI_SIZE:
                return sprintf("Impossible de charger le fichier '%s' : la taille du fichier d�passe la taille maximale autoris�e par le serveur", $file['name']);

            case UPLOAD_ERR_FORM_SIZE:
                return sprintf("Impossible de charger le fichier '%s' : la taille du fichier d�passe la taille maximale autoris�e par le formulaire (MAX_FILE_SIZE)", $file['name']);

            case UPLOAD_ERR_PARTIAL:
                return sprintf("Impossible de charger le fichier '%s' : le fichier n'a �t� que partiellement t�l�charg�",$file['name']);

            case UPLOAD_ERR_NO_FILE: // le input file est vide, pas de fichier � uploader
                return false;

            case UPLOAD_ERR_NO_TMP_DIR:
                return sprintf("Impossible de charger le fichier '%s' : erreur de configuration du serveur, dossier temporaire manquant", $file['name']);

            case UPLOAD_ERR_CANT_WRITE:
                return sprintf("Impossible de charger le fichier '%s' : �chec de l'�criture du fichier sur le disque", $file['name']);

            default:
                return sprintf('Impossible de charger le fichier "%s" : erreur non g�r�e : "%d"', $file['name'], $file['error']);
        }
    }


    /**
     * Retourne la taille du contenu pr�sent dans un fichier compress� au format
     * gzip (.gz).
     *
     * La m�thode d�termine la taille du contenu en consultant les quatre octets
     * qui figurent � la fin du fichier.
     *
     * Remarque :
     * La m�thode v�rifie que le fichier que vous passez en param�tre (quelle
     * que soit son extension) est bien un fichier au format gzip en v�rifiant
     * dans les deux premiers octets du fichier sont conformes � la signature
     * standard d'un fichier gzip.
     *
     * Il vous appartient de v�rifier que le fichier que vous indiquez est bien
     * un fichier compress�. Si vous appellez la m�thode avec autre chose qu'un
     * fichier .gz, elle retournera n'importe quoi.
     *
     * @param string $path le path d'un fichier .gz
     * @return int|false la taille du contenu stock� dans le fichier ou false
     * si une erreur survient (fichier inexistant, fichier vide, ...)
     */
    public static function gzSize($path)
    {
        // La taille du contenu est stock�e dans le fichier .gz � la fin du
        // fichier (champ ISIZE) sous la forme d'un entier 32 bits stock�s en
        // mode "little indian".
        // cf http://tools.ietf.org/html/rfc1952#page-6

        // Ouvre le fichier
        if (!$f=@fopen($path, 'r')) return false;

        // V�rifie que c'est bien un fichier au format gzip
        $id=fread($f, 2);
        if ($id !== "\x1f\x8b")
            die('pas un gz');

        // Va 4 octets avant la fin
        fseek($f, -4, SEEK_END);

        // Lit les quatre octets (une simple chaine pour le moment)
        $size=fread($f, 4);
        if (strlen($size) !== 4) return false;

        // Ferme le fichier
        fclose($f);

        // Convertit en entier
        $size=unpack('V', $size);   // V = little indian

        // unpack retourne un tableau contenant un seul �l�ment
        $size=end($size);

        // Convertit l'entier sign� en entier non sign�
        if ($size <0) $size += 4294967296;

        // Retourne le r�sultat
        return $size;
    }

    /*
     * Encode les caract�res '<', '>', '&', '�' (apostrophe courbe) et '�'
     * (tirets long) en entit�s num�riques hexad�cimales.
     *
     * @param string $xml la chaine � encoder.
     * @return string
     */
    public static function xmlEncode($xml)
    {
        static $table=array
        (
            '<'=>'&#x3C;',
            '>'=>'&#x3E;',
            '&'=>'&#x26;',
            '�'=>'&#x92;',
            '�'=>'&#x96;'
        );

        return strtr($xml, $table);
    }

    /**
     * Met en �vidence les corrections apport�es � une chaine de caract�res en
     * d�tectant les mots qui ont �t� modifi�s.
     *
     * Cette fonction est destin�e � �tre utilis�e avec le correcteur
     * orthographique de xapian : elle met en "surbrillance" les mots qui ont
     * �t� corrig�s.
     *
     * Elle fonctionne en faisant la liste des mots pr�sents dans la chaine
     * originale qui ne figurent pas dans la chaine corrig�e. Elle ajoute
     * ensuite les chaines $before et $after devant et apr�s chacun de ces
     * mots puis retourne le r�sultat.
     *
     * @param string $original la chaine originale.
     * @param string $corrected la chaine corrig�e.
     * @param string $format le format (style sprintf) � utiliser pour mettre
     * en �vidence chacun des mots.
     * @return string la chaine r�sultat.
     */
    public static function highlightCorrections($original, $corrected, $format='<strong>%s</strong>')
    {
        // Cr�e un tableau contenant la liste des mots de $original qui ne sont pas dans $corrected
        $t1=array_flip(Utils::tokenize($original));
        $t2=array_flip(Utils::tokenize($corrected));
        $t=array_diff_key($t2, $t1);

        // G�n�re un tableau de remplacement pour strtr()
        foreach($t as $search=>& $replace)
            $replace=sprintf($format, $search);

        // Met les mots en surbrillance et retourne le r�sultat
        return strtr($corrected, $t);
    }


    /**
     * M�thode de remplacement de la fonction php {@link realpath()}.
     *
     * Le comportement de realpath() a chang� � partir de php 5.2.4 : la fonction peut parfois
     * retourner <code>true</code> pour des fichiers qui n'existe pas.
     * (source : http://php.net/manual/en/function.realpath.php#82770).
     *
     * Bug rencontr� sur Mac OS X (avec Bruno Bernard Simon).
     *
     * @param string $path le chemin du fichier � tester.
     * @return string|false retourne le chemin canonique du fichier ou <code>false</code> si le
     * fichier indiqu� n'existe pas.
     */
    public static function realpath($path)
    {
        if (! file_exists($path)) return false;
        return realpath($path);
    }


    /**
     * Permet de g�rer facilement les singuliers et les pluriels dans une phrase.
     *
     * Exemples d'utilisation :
     * <code>
     * echo Utils::pluralize('{Aucune|Une|%d} occurence{s} trouv�e{s}.', $count);
     * echo Utils::pluralize("{Aucune|Une|%d} t�che{s} {n'a|a|ont} �t� ex�cut�e{s}.", $count);
     * </code>
     *
     * Les blocs entre accolades repr�sentent les parties de la chaine qui seront pluralis�es.
     *
     * La forme g�n�rale est la suivante : <code>{Aucune|Singulier|Pluriel}</code> :
     * - {xxx} : g�n�re 'xxx' si $count > 1, rien sinon.
     * - {xxx|yyy} : g�n�re 'xxx' si $count <= 1, 'yyy' sinon.
     * - {xxx|yyy|zzz} : g�n�re 'xxx' si $count==0, 'yyy' si $count$=1, 'zzz' sinon.
     *
     * Dans la chaine <code>$string</code>, '%d' sera remplac� par <code>$count</code>.
     *
     * Vous pouvez inclure dans la chaine <code>$string</code> d'autre tags sprintf() et passer des
     * arguments suppl�mentaires � la m�thode.
     *
     * Exemple :
     * <code>
     * echo Utils::pluralize('%d t�che{s} ex�cut�e{s} sur %d au total.', $nb, $total);
     * </code>
     *
     * Inspir� et adapt� de :
     * - http://blog.jaysalvat.com/article/gerer-facilement-les-singuliers-pluriels-en-php
     * - http://joshduck.com/blog/2010/08/13/a-php-snipped-for-pluralizing-strings/
     *
     * @param int $count
     * @param string $string
     * @param array $values
     */
    public static function pluralize($string, $count = 1)
    {
        // Convertit les chaines %x
        $values = func_get_args();
        array_shift($values);
        $string = vsprintf($string, $values);

        // Recherche toutes les occurences de {...}
        preg_match_all('~\{(.*?)\}~', $string, $matches);
        foreach($matches[1] as $key=>$value)
        {
            // S�pare les alternatives
            $parts = explode('|', $value);

            // Aucune
            if ($count == 0)
                $replace = (count($parts) === 1) ? '' : $parts[0];

            // Singulier
            elseif ($count == 1)
                $replace = (count($parts) == 1) ? '' : ((count($parts) == 2) ? $parts[0] : $parts[1]);

            // Pluriel
            else
                $replace = (count($parts) == 1) ? $parts[0] : ((count($parts) == 2) ? $parts[1] : $parts[2]);

            // Ins�re le r�sultat
            $string = str_replace($matches[0][$key], $replace , $string);
        }

        // Retourne le r�sultat
        return $string;
    }
}
/*
for ($i=0; $i<=2; $i++)
    echo Utils::pluralize('{Aucune|Une|%d} occurence{s} trouv�e{s} sur %d (%s).<br/>', $i, 100, 'done');

for ($i=0; $i<=2; $i++)
    echo Utils::pluralize('%d t�che{s} ex�cut�e{s} sur %d au total.<br />', $i, 7);

for ($i=0; $i<=2; $i++)
    echo Utils::pluralize("{Aucune|Une|%d} t�che{s} {n'a|a|ont} �t� ex�cut�e{s} sur %d au total.<br />", $i, 7);

    die();
?>
*/