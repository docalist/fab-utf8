<?php
/*
 * Created on 22 mai 06
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
class DefaultModule extends Module
{
	public function actionIndex()
    {
        if (Runtime::$root === Runtime::$fabRoot)
            Template::run('fab.index.html');
        else
            Template::run('index.html');
    }


    public function actionNewApplication($name='myapp', $title='', $path='', $login='', $password='', $password2='', $confirm=false)
    {
        $error = '';
        if ($confirm)
        {
            if ($name==='')
                $error = 'Vous devez indiquer un nom.';
            elseif($path === '')
                $error = 'Vous devez indiquer un r�pertoire.';
//            elseif(file_exists($path))
//                $error = 'Le r�pertoire indiqu� existe d�j�.';
            elseif($login === '')
                $error = 'Vous devez indiquer un identifiant pour le compte administrateur.';
            elseif($password !== $password2)
                $error = 'Les deux mots de passe ne correspondent pas.';

            if ($error === '')
            {
                $this->createApplication($path);
                file_put_contents
                (
                    Utils::makePath($path, 'data', 'users.txt'),
                    "login,password,rights\n$login,$password,Admin\n"
                );

                $url = sprintf
                (
                    '%s%s/%s/web/index.php',
                    Utils::getHost(),
                    rtrim(dirname(dirname(Runtime::$realHome)), '\\/'),
                    $name
                );
                printf('<h2>Votre application est cr��e : <a href="%s">%s</a></h2>', $url, $url);
                return;
            }
        }

        $basedir = strtr(Utils::makePath(dirname(Runtime::$fabRoot), '/'), '\\', '/');
        if ($path === '') $path = $basedir . $name;

        Template::run
        (
            'newapp.html',
            array
            (
                'error' =>$error,
                'basedir' => $basedir,

                'name' => $name,
                'title' => $title,
                'path'=>$path,
                'login'=>$login,
                'password'=>$password,
                'password2'=>$password2
            )
        );
    }

    private function createApplication($path)
    {
        echo "<h1>Cr�ation de l'application $path</h1>";

        // Structure des r�pertoires
        $directories = array
        (
            '/'                 => "r�pertoire racine de l'application",
            '/config'           => "fichiers de configuration de l'application",
            '/data'             => "donn�es de l'application",
            '/data/schemas'     => "sch�mas des bases de donn�es",
            '/data/db'          => "bases de donn�es de l'application",
            '/data/backup'      => "sauvegardes des bases de donn�es",
            '/doc'              => "documentation de l'application",
            '/modules'          => "modules de l'application",
            '/themes'           => "th�mes de l'application",
            '/themes/minimalistic'   => "th�me par d�faut de l'application",
            '/web'              => "partie visible (accessible) de l'application",
            '/web/css'          => "feuilles de styles CSS",
            '/web/css/minimalistic'  => "feuille CSS du th�me par d�faut",
            '/web/css/minimalistic/images'  => "images du th�me",
            '/web/js'           => "librairies Javascript",
            '/web/images'       => "images",
        );

        echo '<h2>Cr�ation de la structure des r�pertoires :</h2>';
        echo '<ul>';
        foreach($directories as $dir=>$role)
        {
            echo "<li><strong>$dir</strong> : $role...";
            if (! Utils::makeDirectory(Utils::makePath($path, $dir)))
                echo 'ERREUR !';
            echo '</li>';
        }
        echo '</ul>';

        // Fichiers de base
        $files=array
        (
            '/config/general.config' => "configuration g�n�rale du site",
            '/config/Admin.config' => "configuration du backoffice",
            '/config/FileBasedSecurity.config' => "param�tres de s�curit�",
            '/web/index.php' => "contr�leur d'acc�s au site (point d'entr�e)",
            '/web/debug.php' => "contr�leur d'acc�s en mode 'debug'",
            '/themes/minimalistic/default.html' => "layout par d�faut (contenu + menu)",
            '/themes/minimalistic/nomenu.html' => "layout par d�faut (pas de menu)",
            '/web/css/minimalistic/minimalistic.css' => "feuille de styles CSS par d�faut",
            '/web/css/minimalistic/images/bg.jpg' => "image du th�me",
            '/web/css/minimalistic/images/bullet.jpg' => "image du th�me",
            '/web/css/minimalistic/images/footer.jpg' => "image du th�me",
            '/web/css/minimalistic/images/header.jpg' => "image du th�me",
            '/web/css/minimalistic/images/sidebarh2.jpg' => "image du th�me",
            '/web/images/flower.jpg' => "image d'exemple",
            '/web/images/bdsp-64x83.png' => "logo Bdsp",
            '/web/images/xapian-powered.png' => "logo Xapian",
        );

        echo "<h2>Cr�ation des fichiers de base de l'application :</h2>";
        echo '<ul>';
        $dir = dirname(__FILE__) . '/FilesToCopy';
        foreach($files as $name=>$role)
        {
            echo "<li><strong>$name</strong> : $role...";
            $extension = Utils::getExtension($name);
            if ($extension === '.php')
            {
                file_put_contents
                (
                    Utils::makePath($path, $name),
                    preg_replace_callback
                    (
                        '~#([a-zA-Z0-9_-]+)#~',
                        array($this, 'expandVarsCallback'),
                        file_get_contents($dir . $name)
                    )
                );
            }
            else
            {
                if (! copy($dir . $name, Utils::makePath($path, $name)))
                    echo 'ERREUR !';
            }
            echo '</li>';
        }
        echo '</ul>';

    }

    private function expandVarsCallback($match)
    {
        switch(strtolower($match[1]))
        {
            case 'fabroot':
                return Runtime::$fabRoot;
            default:
                echo 'Variables non g�r�e : ', $match[1], '<br />';
                return 'Unknown var : #' . $match[1] . '#';
        }
    }
}
?>
