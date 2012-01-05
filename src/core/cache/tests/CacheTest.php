<?php
//require_once ('../Cache.php');

class CacheTest extends AutoTestCase
{

    function setUp()
    {
    }
    function tearDown()
    {
    }
    function testCache()
    {
        // on utilise des noms de fichiers bidons, relatifs au r�pertoire du fichier CacheTest.php
        // les fichiers vont �tre mis en cache dans le cache de l'application, sous r�pertoire fab/core/cache/tests/testdata
        
        $basedir=dirname(__FILE__).'/testdata';
        $cacheDir=Cache::getPath($basedir);

        $dir1="$basedir/group1";
        $dir2="$basedir/group1/subgroup1";

        $path="$dir1/file1.cache";
        $path2="$dir2/file2.cache";

        $data="123456AbCd\tfdsfds";

        // Essaie d'acc�der � un fichier qui n'existe pas
        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe pas d�j�");
        $this->assertFalse(Cache::get($path), "si on essaie de le lire, cache::get() retourne false");
        $this->assertTrue(Cache::lastModified($path)===0, "et sa date de derni�re modification vaut z�ro");
        
        // Ajoute file1.cache
        $this->assertTrue(Cache::set($path, $data),"Ajout du fichier [$path]");
        $this->assertTrue(Cache::has($path), "fichier [$path] existe");
        $this->assertTrue(Cache::get($path)===$data, "les donn�es sont ok");
        $date=Cache::lastModified($path);
        $this->assertTrue($date<=time() && $date>=time()-1, "et sa date de derni�re modification semble correcte");
        
        // Supprime le fichier
        Cache::remove($path);
        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe plus");
        
        // Comme le fichier �tait le seul fichier ajout�, le fait de le supprimer supprime les r�pertoires
        $this->assertFalse(is_dir($dir2), "r�p [$dir2] n'existe pas");
        $this->assertFalse(is_dir($dir1), "r�p [$dir1] n'existe pas");
        clearstatcache();
//        $this->assertFalse(is_dir("$cacheDir"), "r�p [$cacheDir] (sscacheDir) n'existe plus");
        
        // maintenant ajoute deux fichiers
        $this->assertTrue(Cache::set($path, $data), "Ajout du fichier [$path]");
        $this->assertTrue(Cache::set($path2, $data), "Ajout du fichier [$path2]");
        $this->assertTrue(Cache::has($path), "fichier [$path] existe");
        $this->assertTrue(Cache::get($path)===$data, "les donn�es sont ok");
        $this->assertTrue(Cache::has($path2), "fichier [$path2] existe");
        $this->assertTrue(Cache::get($path2)===$data, "les donn�es sont ok");

        // Supprime le premier des deux fichiers
        Cache::remove($path);
        clearstatcache();
        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe plus");
        $this->assertTrue(Cache::has($path2), "fichier [$path2] existe encore");
        
        // Le r�pertoire du premier fichier a d� �tre supprim� mais pas celui du second
        $this->assertFalse(is_dir($dir1), "r�p [$dir1] n'existe plus");
        clearstatcache();
//        $this->assertTrue(is_dir($dir2), "r�p [".Cache::getPath($dir2)."] existe ssencore");

        // Supprime le second fichier
        Cache::remove($path2);
        $this->assertFalse(Cache::has($path2), "fichier [$path2] n'existe plus");
        $this->assertFalse(is_dir("$cacheDir$dir1"), "r�p [$cacheDir$dir1] n'existe plus");
        
        // Ajoute � nouveau les deux fichiers
        $this->assertTrue(Cache::set($path, $data), "Ajout du fichier [$path]");
        $this->assertTrue(Cache::set($path2, $data), "Ajout du fichier [$path2]");

        touch(Cache::getPath($path), time()-1000);
        $this->assertTrue(Cache::has($path));
        $this->assertFalse(Cache::has($path, time()-500), "$path n'est pas � jour");
//        Cache::clear('', time()-500);
//        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe plus");
//        $this->assertTrue(Cache::has($path2), "fichier [$path] existe encore");
        
//        $this->assertTrue(Cache::set($path, $data), "Ajout du fichier [$path]");
//        $this->assertTrue(Cache::set($path2, $data),"Ajout du fichier [$path2]");
//        Cache::clear();        
//        $this->assertFalse(Cache::has($path), "fichier [$path] n'existe plus");
//        $this->assertFalse(Cache::has($path2), "fichier [$path] n'existe plus");

        Cache::remove($path);
        Cache::remove($path2);

    }
}
?>