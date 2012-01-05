<?php

/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: AdminFiles.php 882 2008-07-22 13:40:06Z daniel.menard.bdsp $
 */

/**
 * Module d'administration permettant de g�rer les fichiers et les dossiers 
 * pr�sents au sein d'un r�pertoire.
 * 
 * Le module offre des fonctions permettant de {@link actionNewFile() cr�er}, de 
 * {@link actionRename() renommer}, de {@link actionEdit() modifier}, 
 * de {@link actionCopy() copier}, de {@link actionDelete() supprimer} et de 
 * {@link actionDownload() t�l�charger} les fichiers.
 * 
 * Il permet �galement de {@link getBackUrl() naviguer} dans l'arborescence des 
 * r�pertoires et offre des fonctions permettant de {@link actionCopy() copier}, 
 * de {@link actionRename() renommer} et de {@link actionDelete() supprimer} des
 * r�pertoires.
 * 
 * Le module travaille par rapport � un r�pertoire de travail qui est indiqu� 
 * par la m�thode {@link getDirectory()}. Il n'est pas possible de "remonter"
 * plus haut que ce r�pertoire, ni d'intervenir sur des fichiers situ� en dehors
 * de l'arborescence d�finie par ce r�pertoire.
 * 
 * N�anmoins, il est possible de copier un fichier "ext�rieur" dans 
 * l'arborescence du r�pertoire de travail en utilisant la m�thode 
 * {@link actionCopyFrom()}.
 * 
 * <code>AdminFiles</code> peut �tre utilis� directement ou servir de classe 
 * anc�tre � un module d'administration ayant besoin d'offrir des actions de 
 * manipulation de fichiers et de r�pertoires.
 * 
 * C'est le cas par exemple des modules d'administration {@link AdminCache}, 
 * {@link AdminConfig}, {@link AdminModules} et {@link AdminSchemas}.
 * 
 * @package     fab
 * @subpackage  Admin
 */
class AdminFiles extends Admin
{

    /**
     * Retourne le path complet du r�pertoire sur lequel va travailler le module.
     * 
     * Le path retourn� est construit � partir des �lements suivants :
     * - le r�pertoire racine de l'application ({@link Runtime::$root})
     * - le r�pertoire indiqu� dans la cl� <code><directory></code> de la config
     * - le r�pertoire �ventuel indiqu� dans le param�tre <code>directory</code>
     *   indiqu� en query string
     * 
     * Le chemin retourn� contient toujours un slash final (un antislash sous
     * windows).
     * 
     * Cette m�thode peut �tre surcharg�e par les modules d'administration
     * descendants si une impl�mentation diff�rente est souhait�e.
     *
     * @throws Exception est g�n�r�e si :
     * - le r�pertoire obtenu contient des s�quences de la forme 
     *   <code>/../</code> (ces s�quences sont interdites pour emp�cher 
     *   de remonter plus haut que le r�pertoire racine de l'application ou que
     *   le r�pertoire de travail indiqu� dans le fichier de configuration).
     * - le r�pertoire obtenu n'existe pas ou d�signe autre chose qu'un 
     *   r�pertoire.
     * 
     * @return string le path exact (absolu) du r�pertoire de travail obtenu.
     */
    public function getDirectory()
    {
        // Construit le path
        $path=Utils::makePath
        (
            Runtime::$root,                     // On ne travaille que dans l'application 
            Config::get('directory'),           // R�pertoire indiqu� dans la config
            $this->request->get('directory'),   // R�pertoire �ventuel pass� en param�tre
            DIRECTORY_SEPARATOR                 // Garantit qu'on a un slash final
        );
        
        // V�rifie qu'on n'a pas de '..'
        $this->checkPath($path, 'R�pertoire de travail');
        
        // V�rifie que c'est un r�pertoire et que celui-ci existe
        if (!is_dir($path))
            throw new Exception("Le r�pertoire indiqu� n'existe pas.");
            
        // Retourne le r�sultat
        return $path;
    }

    /**
     * Retourne un tableau permettant de construire un fil d'ariane 
     * (breadcrumbs).
     * 
     * {@inheritdoc}
     * 
     * La m�thode ajoute au tableau retourn� par la classe parente le nom du
     * fichier �ventuel pass� en param�tre dans <code>$file</code>.
     * 
     * @return array
     */
    protected function getBreadCrumbsArray()
    {
        // Initialement : lien vers /admin et lien vers page index du module en cours
        $breadCrumbs=parent::getBreadCrumbsArray();

        // Ajoute chacun des dossiers composant le r�pertoire en cours  
        $path=Utils::makePath($this->request->get('directory'));
        $parts=preg_split('~'.preg_quote(DIRECTORY_SEPARATOR, '~').'~', $path, -1, PREG_SPLIT_NO_EMPTY);

        // Pour chaque dossier, ajoute un lien vers le dossier
        $dir='';
        foreach($parts as $part)
        {
            $dir .= $part . DIRECTORY_SEPARATOR;
            $breadCrumbs['index?directory='.$dir]=$part;
        }

        // Si on a un nom de fichier en param�tre, on l'ajoute
        if ($file=$this->request->get('file'))
            $breadCrumbs[$this->request->getUrl()]=$file;
        
        return $breadCrumbs;    
    }
    
    /**
     * V�rifie que le path indiqu� en param�tre ne contient pas de s�quences
     * de la forme <code>../</code>.
     * 
     * Ces s�quences sont interdites pour emp�cher de remonter plus haut
     * que le r�pertoire racine de l'application ou que le r�pertoire de travail 
     * indiqu� dans le fichier de configuration.
     *
     * @param string $path le path ou le nom de fichier � tester.
     * @param string $what un message optionnel qui sera affich� dans la
     * description de l'exception g�n�r�e.
     * 
     * @throws Exception si le path contient des s�quences interdites. 
     */
    protected function checkPath($path, $what)
    {
         // V�rifie qu'on n'a pas de s�quences /../ dans le path
        if (strpos($path, '..'.DIRECTORY_SEPARATOR) !== false)
            throw new Exception($what . " : les s�quences de la forme '..' ne sont pas autoris�es.");
    }
    
    
    /**
     * Retourne le r�pertoire parent du r�pertoire de travail indiqu� par 
     * {@link getDirectory()}.
     * 
     * La m�thode si un sous-r�pertoire a �t� indiqu� dans le param�tre
     * <code>directory</code> de la {@link Request requ�te en cours}.
     * 
     * Si c'est le cas, elle retourne le r�pertoire parent de ce r�pertoire.
     * 
     * @return false|string la fonction retourne :
     * - <code>false</code> si aucun sous-r�pertoire n'a �t� indiqu� (i.e. on
     *   est d�j� "� la racine") ;
     * - le path exact du r�pertoire parent si un sous-r�pertoire (non vide) a 
     *   �t� indiqu� dans la requ�te,
     * - une chaine vide si le r�pertoire parent obtenu correspond � la racine,
     *   c'est-�-dire au r�pertoire retourn� par {@link getDirectory()}.
     */
    public function getParentDirectory()
    {
        $path=$this->request->get('directory', '');
        if ($path==='') return false;
        $path=strtr($path, '\\', '/');
        $path=rtrim($path, '/');
        $pt=strrpos($path, '/');
        return substr($path, 0, $pt);
    }
    
    
    /**
     * Retourne la liste des fichiers et des dossiers pr�sents dans le 
     * r�pertoire de travail.
     * 
     * Le tableau obtenu liste en premier les fichiers (tri�s par ordre 
     * alphab�tique) puis les dossiers (�galement tri�s).
     * 
     * Les cl�s des entr�es du tableau contiennent le path complet 
     * du fichier ou du dossier, la valeur associ�e contient uniquement
     * le nom de base.
     * 
     * Seuls les fichiers et les dossiers correspondant au masque <code>*</code>
     * sont retourn�s. Cela signifie (entre autres) que les fichiers dont le nom
     * commence par un point (<code>.project</code>, <code>.htaccess</code>, ...) ne
     * seront pas retourn�s. Idem pour les dossiers (<code>.</code>, 
     * <code>..</code>, <code>.svn</code>, ...)
     * 
     * @return array le tableau obtenu ou un tableau vide si le r�pertoire
     * de travail est vide.
     */
    public function getFiles()
    {
        $files=array();
        $dirs=array();
        
        // Dresse la liste des fichiers et des dossiers
        foreach(glob($this->getDirectory() . '*') as $path)
        {
            if (is_file($path) || is_link($path))
                $files[$path]=basename($path);
            else
                $dirs[$path]=basename($path);
        }
        
        // Trie les fichiers par ordre alphab�tique
        ksort($files, SORT_LOCALE_STRING);
        
        // Trie les dossiers par ordre alphab�tique
        ksort($dirs, SORT_LOCALE_STRING);
        
        // Concat�ne les fichiers et les dossiers et retourne le tout
        return $files + $dirs;
    }
    
    /**
     * D�termine l'icone � afficher pour repr�senter le type du fichier ou du
     * dossier pass� en param�tre.
     *  
     * Si le path indiqu� d�signe un r�pertoire, une icone g�n�rique 
     * repr�sentant un dossier est retourn�e.
     * 
     * Dans le cas contraire, elle examine l'extension pr�sente dans le nom du 
     * fichier (<code>.php</code>, <code>.xml</code>, etc) pour d�terminer le
     * type de ce fichier et retourne une icone en cons�quence.
     * 
     * Si le fichier ne comporte pas d'extension ou si cette extension n'est
     * pas reconnue, une icone g�n�rique repr�sentant un fichier est retourn�e.
     *
     * @param string $path
     * @return string une url de la forme 
     * <code>/FawWeb/modules/AdminFiles/images/filetypes/icon.png</code>
     */
    public function getFileIcon($path)
    {
        // Path du r�pertoire contenant les icones
        $icon='/FabWeb/modules/AdminFiles/images/filetypes/';
        
        // Cas d'un dossier
        if (is_dir($path))
            return $icon . 'folder.png';

        // Fichier : regarde l'extension
        switch(strtolower(Utils::getExtension($path)))
        {
            // fixme: mettre tout �a dans la config (cl� filetypes avec une cl� par type)
            case '.config': 
                $icon.='config.png'; 
                break;
            case '.css': 
                $icon.='css.png'; 
                break;
            case '.gif': 
                $icon.='gif.png'; 
                break;
            case '.htm':
            case '.html':
                $icon.='html.png'; 
                break;
            case '.jpg': 
                $icon.='jpg.png'; 
                break;
            case '.pdf': 
                $icon.='pdf.png'; 
                break;
            case '.php': 
                $icon.='php.png'; 
                break;
            case '.png': 
                $icon.='png.png'; 
                break;
            case '.xml': 
                $icon.='xml.png'; 
                break;
            case '.zip': 
                $icon.='zip.png'; 
                break;
            case '.config': 
                $icon.='config.png'; 
                break;
            default: 
                $icon.='default.png';
        }
        
        // Retourne le r�sultat
        return $icon;
    }

    
    /**
     * D�termine le type de coloration syntaxique � appliquer au fichier dont le
     * nom est pass� en param�tre.
     * 
     * Cette m�thode permet de param�trer l'�diteur de code (actuellement, il 
     * s'agit de {@link http://www.cdolivet.net/editarea/ EditArea}) utilis�
     * par l'action {@link actionEdit() Edit}.
     * 
     * Elle retourne le nom d'une syntaxe � utiliser pour afficher correctement
     * le fichier dans l'�diteur, en se basant sur l'extension du fichier pass� 
     * en param�tre pour d�terminer le type du fichier.
     *
     * @param string $file le nom ou le path d'un fichier.
     * 
     * Remarque :
     * Aucun test d'existence n'est fait sur le path indiqu�.
     * 
     * @return string le code de l'un des fichiers de syntaxe support�s par 
     * {@link http://www.cdolivet.net/editarea/ EditArea}
     */
    public function getEditorSyntax($file)
    {
        switch (strtolower(Utils::getExtension($file)))
        {
            case '.htm':
            case '.html':
                 return 'html';
            case '.css': 
                return 'css';
            case '.js':
                return 'js';
            case '.php':
                return 'php';
            case '.xml': 
                return 'xml';
            case '.config':
                return 'xml';
            default: return 'brainfuck';
        }
    }

    
    /**
     * Redirige l'utilisateur vers la page d'o� il vient. 
     *
     * L'utilisateur est redirig� vers l'action index du module, en indiquant
     * �ventuellement une ancre sur laquelle positionner l'utilisateur.
     * 
     * @param string $file le nom d'un fichier � utiliser comme ancre. 
     */
    private function goBack($file='')
    {
        Runtime::redirect($this->getBackUrl($file));
    }

    
    /**
     * Retourne une url permettant de rediriger l'utilisateur vers la page 
     * d'o� il vient. 
     *
     * L'utilisateur est redirig� vers l'action index du module, en indiquant
     * �ventuellement une ancre sur laquelle positionner l'utilisateur.
     * 
     * @param string $file le nom d'un fichier � utiliser comme ancre.
     * @return string 
     */
    public function getBackUrl($file='')
    {
        $url=$this->request->setAction('Index')->keepOnly('directory');
        if ($url->get('directory')==='') $url->clear('directory');
        $url=$url->getUrl();
        
        if ($file) $url.='#'.$file;
        return $url;
    }


    /**
     * Page d'accueil.
     * 
     * <code>actionIndex</code> repr�sente la page d'accueil du module 
     * d'administration des fichiers.
     * 
     * Elle liste tous les fichiers et les dossiers pr�sents dans le 
     * {@link getDirectory() r�pertoire de travail}.
     * 
     * Le template utilis� pour l'affichage est indiqu� dans la cl�
     * <code><template></code> du fichier de configuration.
     */
    public function actionIndex()
    {
        Template::run(Config::get('template'));
    }


    /**
     * Cr�e un nouveau fichier.
     * 
     * - demande le nom du fichier � cr�er,
     * - v�rifie que ce nom de fichier est correct,
     * - v�rifie qu'il n'existe pas d�j� un fichier ou un r�pertoire portant
     *   ce nom,
     * - cr�e le fichier,
     * - redirige l'utilisateur vers la page d'accueil.
     * 
     * Si aucun nom de fichier n'a �t� pass� en param�tre, un nom par d�faut est 
     * propos� � l'utilisateur en utilisant la valeur indiqu�e dans la cl� 
     * <code><newfilename></code> du fichier de configuration et la m�thode 
     * appelle ensuite le template indiqu� dans la cl� <code><template></code> 
     * du fichier de configuration en lui passant en param�tre le nom du fichier 
     * (<code>file</code>).
     * 
     * Si le nom choisi n'est pas valide ou est d�j� utilis�, le m�me template
     * est r�affich�, en indiquant en plus le message d'erreur obtenu 
     * (<code>error</code>).
     * 
     * Sinon, la m�thode cr�e le fichier (vide). Si une erreur survient � ce 
     * stade (droits insuffisants, partition pleine...), celle-ci est simplement 
     * affich�e. Dans le cas contraire, l'utilisateur est redirig� vers la page 
     * d'accueil du site et est positionn� sur le nouveau fichier cr��. 
     * 
     * @param string $file
     */
    public function actionNewFile($file='')
    {
        $path=$this->getDirectory().$file;
        
        $error='';
        
        // V�rifie que le fichier indiqu� n'existe pas d�j�
        if ($file !== '')
        {
            // V�rifie qu'on n'a pas de '..'
            $this->checkPath($file, 'Nom du nouveau fichier');
        
            if (file_exists($path))
            {
                if (is_dir($path))
                    $error="Il existe d�j� un dossier nomm� $file.";
                else
                    $error="Il existe d�j� un fichier nomm� $file.";
            }
        }
        
        // Demande le nom du fichier � cr�er
        if ($file==='' || $error !='')
        {
            if ($file==='') $file=Config::get('newfilename');
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'error'=>$error)
            );
            return;
        }
        
        // Cr�e le fichier
        if (false === @file_put_contents($path, ''))
        {
            echo 'La cr�ation du fichier ', $file, ' a �chou�.';
            return;
        }
                
        // Redirige vers la page d'accueil
        $this->goBack($file);
    }
    

    /**
     * Cr�e un nouveau dossier.
     * 
     * - demande le nom du dossier � cr�er,
     * - v�rifie que ce nom de dossier est correct,
     * - v�rifie qu'il n'existe pas d�j� un fichier ou un r�pertoire portant
     *   ce nom,
     * - cr�e le dossier,
     * - redirige l'utilisateur vers la page d'accueil.
     * 
     * Si aucun nom n'a �t� pass� en param�tre, un nom par d�faut est 
     * propos� � l'utilisateur en utilisant la valeur indiqu�e dans la cl� 
     * <code><newfoldername></code> du fichier de configuration et la m�thode 
     * appelle ensuite le template indiqu� dans la cl� <code><template></code> 
     * du fichier de configuration en lui passant en param�tre le nom du dossier 
     * (<code>file</code>).
     * 
     * Si le nom choisi n'est pas valide ou est d�j� utilis�, le m�me template
     * est r�affich�, en indiquant en plus le message d'erreur obtenu 
     * (<code>error</code>).
     * 
     * Sinon, la m�thode cr�e le dossier. Si une erreur survient � ce 
     * stade (droits insuffisants, partition pleine...), celle-ci est simplement 
     * affich�e. Dans le cas contraire, l'utilisateur est redirig� vers la page 
     * d'accueil du site et est positionn� sur le nouveau dossier cr��. 
     * 
     * @param string $file
     */
    public function actionNewFolder($file='')
    {
        $path=$this->getDirectory().$file;
        
        $error='';
        
        // V�rifie que le dossier indiqu� n'existe pas d�j�
        if ($file !== '')
        {
            // V�rifie qu'on n'a pas de '..'
            $this->checkPath($file, 'Nom du nouveau dossier');
        
            if (file_exists($path))
            {
                if (is_dir($path))
                    $error="Il existe d�j� un dossier nomm� $file.";
                else
                    $error="Il existe d�j� un fichier nomm� $file.";
            }
        }
        
        // Demande le nom du fichier � cr�er
        if ($file==='' || $error !='')
        {
            if ($file==='') $file=Config::get('newfoldername');
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'error'=>$error)
            );
            return;
        }
        
        // Cr�e le fichier ou le dossier
        if (false=== @mkdir($path))
        {
            echo 'La cr�ation du r�pertoire ', $file, ' a �chou�.';
            return;
        }
                
        // Redirige vers la page d'accueil
        $this->goBack($file);
    }
    
    
    /**
     * Renomme un fichier ou un dossier existant.
     * 
     * - demande le nouveau nom du fichier ou du dossier � renommer,
     * - v�rifie que ce nom est correct,
     * - v�rifie qu'il n'existe pas d�j� un fichier ou un r�pertoire portant
     *   ce nom,
     * - renomme le fichier ou le dossier,
     * - redirige l'utilisateur vers la page d'accueil.
     * 
     * Si le nouveau nom n'a pas �t� pass� en param�tre, la m�thode 
     * appelle le template indiqu� dans la cl� <code><template></code> 
     * du fichier de configuration en lui passant en param�tre le nom du fichier 
     * ou du dossier � renommer (<code>file</code>).
     * 
     * Si le nouveau nom choisi par l'utilisateur n'est pas valide ou est d�j� 
     * utilis�, le m�me template est r�affich�, en indiquant en plus le nouveau
     * nom (<code>newName</code>) et le message d'erreur obtenu 
     * (<code>error</code>).
     * 
     * Sinon, la m�thode renomme le fichier ou le dossier. Si une erreur 
     * survient � ce stade (droits insuffisants par exemple), celle-ci est 
     * simplement affich�e. Dans le cas contraire, l'utilisateur est redirig� 
     * vers la page d'accueil du site et est positionn� sur le fichier ou le
     * dossier renomm�. 
     * 
     * @param string $file
     * @param string $newName
     * 
     * @throws Exception si le fichier indiqu� n'existe pas.
     * 
     */
    public function actionRename($file, $newName='')
    {
        $this->checkPath($file, 'Fichier � renommer');
    
        $path=$this->getDirectory().$file;
        
        if (! file_exists($path))
            throw new Exception("Le fichier $file n'existe pas.");

        $error='';
        if ($newName !== '')
        {
            // V�rifie qu'on n'a pas de '..'
            $this->checkPath($newName, 'Nouveau nom');
        
            if ($file !== $newName && file_exists($this->getDirectory().$newName))
                $error='Il existe d�j� un fichier ou un dossier portant ce nom.';
        }
                    
        if ($newName==='' || $error !='')
        {
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'newName'=>$newName, 'error'=>$error)
            );
            return;
        }
        
        if ($file !==$newName)
        {
            if (!rename($path, $this->getDirectory().$newName))
            {
                echo 'Le renommage a �chou�';
                return;
            }
        }
                
        // Redirige vers la page d'accueil
        $this->goBack($newName);
    }

    
    /**
     * Fait une copie sous un nouveau nom d'un fichier ou d'un dossier existant.
     * 
     * - demande le nom du nouveau fichier ou du nouveau dossier,
     * - v�rifie que ce nom est correct,
     * - v�rifie qu'il n'existe pas d�j� un fichier ou un r�pertoire portant
     *   ce nom,
     * - fait une copie,
     * - redirige l'utilisateur vers la page d'accueil.
     * 
     * Si le nom du fichier ou du dossier � cr�er n'a pas �t� pass� en param�tre, 
     * la m�thode appelle le template indiqu� dans la cl� <code><template></code> 
     * du fichier de configuration en lui passant en param�tre le nom du fichier 
     * ou du dossier � renommer (<code>file</code>) et une suggestion de nom
     * (<code>newName</code>) qui par d�faut sera "copie de "+le nom du fichier
     * ou du dossier existant.
     * 
     * Si le nouveau nom choisi par l'utilisateur n'est pas valide ou est d�j� 
     * utilis�, le m�me template est r�affich�, en indiquant en plus le message 
     * d'erreur obtenu (<code>error</code>).
     * 
     * Sinon, la m�thode fait une copie du fichier ou du dossier. Si une erreur 
     * survient � ce stade (droits ou espace disque insuffisants par exemple), 
     * celle-ci est simplement affich�e. Dans le cas contraire, l'utilisateur 
     * est redirig� vers la page d'accueil du site et est positionn� sur le 
     * fichier ou le dossier cr��. 
     * 
     * @param string $file
     * @param string $newName
     * 
     * @throws Exception si le fichier indiqu� n'existe pas.
     */
    public function actionCopy($file, $newName='')
    {
        $this->checkPath($file, 'Fichier � copier');
        
        $path = $this->getDirectory().$file;
        
        if (! file_exists($path))
            throw new Exception("Le fichier $file n'existe pas.");

        $error='';
        if ($newName!=='')
        { 
            // V�rifie qu'on n'a pas de '..'
            $this->checkPath($newName, 'Nouveau nom');
        
            if ($file !==$newName && file_exists($this->getDirectory().$newName))
                $error='Il existe d�j� un fichier portant ce nom.';
        }
        
        if ($newName==='' || $error !='')
        {
            if ($newName==='') $newName='copie de '.$file;
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'newName'=>$newName, 'error'=>$error)
            );
            return;
        }
        
        if ($file !==$newName)
        {
            if (!$this->copyr($path, $this->getDirectory().$newName))
            {
                echo 'La copie a �chou�';
                return;
            }
        }
                
        // Redirige vers la page d'accueil
        $this->goBack($newName);
    }

    
    /**
     * Copy a file, or recursively copy a folder and its contents
     *
     * @author      Aidan Lister {@link aidan@php.net}
     * @version     1.0.1
     * @link        http://aidanlister.com/repos/v/function.copyr.php
     * @param       string   $source    Source path
     * @param       string   $dest      Destination path
     * @return      bool     Returns TRUE on success, FALSE on failure
     */
    private function copyr($source, $dest)
    {
        // Simple copy for a file
        if (is_file($source)) {
            return copy($source, $dest);
        }
     
        // Make destination directory
        if (!is_dir($dest)) {
            mkdir($dest);
        }
     
        // Loop through the folder
        $dir = dir($source);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }
     
            // Deep copy directories
            if ($dest !== "$source/$entry") {
                $this->copyr("$source/$entry", "$dest/$entry");
            }
        }
     
        // Clean up
        $dir->close();
        return true;
    }    

    
    /**
     * Copie un fichier � partir d'un autre r�pertoire.
     * 
     * Cette m�thode fonctionne comme {@link actionCopy() l'action Copy} si 
     * ce n'est que le fichier � copier doit �tre fournit sous la forme d'un
     * path complet.
     * 
     * @param string $file
     * @param string $newName
     * 
     * @todo L'action {@link actionCopy Copy} devrait savoir � la fois copier un 
     * fichier local et un fichier ext�rieur � l'arborescence du r�pertoire de 
     * travail.
     * 
     * A l'avenir cette m�thode sera supprim�e, il ne restera que l'action Copy.
     * 
     * @throws Exception si le fichier indiqu� n'existe pas.
     */
    public function actionCopyFrom($file, $newName='')
    {
        if (! file_exists($file))
            throw new Exception("Le fichier $file n'existe pas.");

        $error='';
        if ($newName!=='' && file_exists($this->getDirectory().$newName))
            $error='Il existe d�j� un fichier portant ce nom.';
                    
        if ($newName==='' || $error !='')
        {
            Template::run
            (
                Config::get('template'),
                array('file'=>$file, 'newName'=>$newName, 'error'=>$error)
            );
            return;
        }
        
        if ($file !==$newName)
        {
            if (!copy($file, $this->getDirectory().$newName))
            {
                echo 'La copie a �chou�';
                return;
            }
        }
                
        // Redirige vers la page d'accueil
        $this->goBack($newName);
    }
    
    
    /**
     * Supprime d�finitivement un fichier ou un dossier existant.
     * 
     * - v�rifie que le fichier ou le dossier demand� existe
     * - demande confirmation � l'utilisateur
     * - fait le fichier ou le dossier,
     * - redirige l'utilisateur vers la page d'accueil.
     * 
     * La demande de confirmation consiste � ex�cuter le template indiqu� dans 
     * la cl� <code><template></code> du fichier de configuration en lui passant 
     * en param�tre le nom du fichier ou du dossier � supprimer 
     * (<code>file</code>).
     * 
     * Ce template doit r�-appeller l'action Delete avec 
     * <code>confirm</code> � <code>true</code>.
     * 
     * Sinon, la m�thode proc�de alors � la destruction du fichier ou du dossier.
     * 
     * Si une erreur survient � ce stade celle-ci est simplement affich�e.
     * 
     * Dans le cas contraire, l'utilisateur est redirig� vers la page d'accueil 
     * du site. 
     * 
     * @param string $file
     * @param bool $confirm
     * 
     * @throws Exception si le fichier indiqu� n'existe pas.
     */
    public function actionDelete($file, $confirm=false)
    {
        $this->checkPath($file, 'Fichier � supprimer');
        
        if (! file_exists($this->getDirectory().$file))
            throw new Exception("Le fichier $file n'existe pas.");

        if (! $confirm)
        {
            Template::run
            (
                Config::get('template'),
                array('file'=>$file)
            );
            return;
        }
        
        if (!$this->delete($this->getDirectory().$file))
        {
            echo 'La suppression a �chou�';
            return;
        }
                
        // Redirige vers la page d'accueil
        $this->goBack();
    }

    
    /**
     * Delete a file, or a folder and its contents
     *
     * @author      Aidan Lister {@link aidan@php.net}
     * @version     1.0.3
     * @link        http://aidanlister.com/repos/v/function.rmdirr.php
     * @param       string   $dirname    Directory to delete
     * @return      bool     Returns TRUE on success, FALSE on failure
     */
    private function delete($dirname)
    {
        // Sanity check
        if (!file_exists($dirname)) {
            return false;
        }
     
        // Simple delete for a file
        if (is_file($dirname) || is_link($dirname)) {
            return unlink($dirname);
        }
     
        // Loop through the folder
        $dir = dir($dirname);
        while (false !== $entry = $dir->read()) {
            // Skip pointers
            if ($entry == '.' || $entry == '..') {
                continue;
            }
     
            // Recurse
            $this->delete($dirname . DIRECTORY_SEPARATOR . $entry);
        }
     
        // Clean up
        $dir->close();
        return rmdir($dirname);
    }    
    
    
    /**
     * T�l�charge un fichier.
     * 
     * La m�thode v�rifie que le nom de fichier indiqu� existe et est valide
     * puis transmet le contenu du fichier en d�finissant les ent�tes http
     * ({@link http://www.ietf.org/rfc/rfc1049.txt content-type} et 
     * {@link http://www.ietf.org/rfc/rfc1806.txt content-disposition}) de 
     * mani�re � ce que le navigateur propose � l'utilisateur d'enregistrer 
     * ou d'ouvrir le fichier.
     * 
     * Le type mime du fichier retourn� dans les ent�tes http est d�termin�
     * en utilisant la fonction {Utils::mimeType()}.
     * 
     * @param string $file
     * 
     * @throws Exception si le fichier indiqu� n'existe pas.
     */
    public function actionDownload($file)
    {
        $this->checkPath($file, 'Fichier � t�l�charger');
        if (! file_exists($this->getDirectory().$file))
            throw new Exception("Le fichier $file n'existe pas.");
        header('content-type: '.Utils::mimeType($file));
        header('content-disposition: attachment; filename="'.$file.'"');
        readfile($this->getDirectory().$file);
    }

    
    /**
     * Charge le fichier indiqu� dans l'�diteur de code source.
     *
     * La m�thode v�rifie que le fichier est correct puis ex�cute le template
     * indiqu� dans la cl� <code><template></code> du fichier de configuration
     * en lui passant le nom du fichier �dit� (<code>file</code>) et son 
     * contenu (<code>content</code>).
     * 
     * Actuellement, l'�diteur de code ne sait g�rer que des fichiers encod�s
     * en ISO-8859-1. Si la cl� <code><utf8></code> du fichier de configuration 
     * est � <code>true</code> (ce qui signifie "les fichiers du r�pertoire de
     * travail sont encod�s en utf-8"), le contenu du fichier sera r�-encod� en 
     * ISO-8859-1 avant d'�tre fournit au template (la fonction 
     * {@link http://php.net/utf8_decode utf8_decode()} de php est utilis�e.
     * 
     * @param string $file
     * 
     * @throws Exception si le fichier indiqu� n'existe pas.
     */
    public function actionEdit($file)
    {
        $this->checkPath($file, 'Fichier � modifier');
        
        // V�rifie que le fichier indiqu� existe
        $path=$this->getDirectory().$file;
        if (! file_exists($path))
            throw new Exception("Le fichier $file n'existe pas.");
        
        // Charge le fichier
        $content=file_get_contents($path);

        // D�code l'utf8 si demand� dans la config
        if (Config::get('utf8'))
            $content=utf8_decode($content);
            
        // Charge le fichier dans l'�diteur
        Template::run
        (
            Config::get('template'),
            array
            (
                'file'=>$file,
                'content'=>$content,
            )
        );
    }

    /**
     * Sauvegarde le contenu d'un fichier modifi� dans 
     * {@link actionEdit() l'�diteur de code}.
     *
     * La m�thode v�rifie que le fichier indiqu� existe puis sauvegarde le 
     * contenu pass� en param�tre dans ce fichier.
     * 
     * Si la cl� <code><utf8></code> du fichier de configuration est � 
     * <code>true</code> (ce qui signifie "les fichiers du r�pertoire de
     * travail sont encod�s en utf-8"), le contenu du fichier sera encod� en 
     * utf-8 avant d'�tre sauvegard� (la fonction 
     * {@link http://php.net/utf8_encode utf8_encode()} de php est utilis�e.
     * 
     * @param string $file
     * @param string $content
     * 
     * @throws Exception si le fichier indiqu� n'existe pas.
     */
    public function actionSave($file, $content)
    {
        $this->checkPath($file, 'Fichier � modifier');
    
        // V�rifie que le fichier indiqu� existe
        $path=$this->getDirectory().$file;
        if (! file_exists($path))
            throw new Exception("Le fichier $file n'existe pas.");
        
        // Encode l'utf8 si demand� dans la config
        if (Config::get('utf8'))
            $content=utf8_encode($content);
        
        // Sauvegarde le fichier
        file_put_contents($path, $content);
        
        // Redirige vers la page d'accueil
        $this->goBack($file);
    }
    
    public function actionUpload()
    {
        $error='';
        if (count($_FILES)===0)
        {
            Template::run('upload.html', array('error'=>$error));
            return;
        }
        
        // R�cup�re le fichier upload�
        $file=$_FILES['file'];
        $name=$file['name'];
        
        // V�rifie que le nom n'a pas �t� "bidouill�" (s�quences ".." par exemple)
        $this->checkPath($name, 'Nom du fichier � envoyer');
        
        // D�termine le r�pertoire de destination
        $dir=$this->getDirectory();
        
        // S'il existe d�j� un fichier portant ce nom, ajoute un num�ro
        $i=1;
        while (file_exists($dir.$name))
        {
            $i++;
            $name=Utils::setExtension($file['name']) . '-' . $i . Utils::getExtension($file['name']);
        }
        
        // Copie le fichier upload�
        $error=Utils::uploadFile($file, $dir.$name, null);
        
        if (is_string($error) || $error===false)
        {
            if ($error===false) $error="Vous n'avez pas s�lectionn� le fichier � envoyer.";
            Template::run('upload.html', array('error'=>$error));
            return;
        }
        
        // Si le fichier a �t� renomm�, indique le nouveau nom a l'utilisateur
        if ($name !== $file['name'])
        {
            Template::Run
            (
                'uploaded.html',
                array
                (
                    'file'=>$file['name'],
                    'newname'=>$name
                )
            );
            return;
        }
        
        // Sinon, redirige l'utilisateur vers le fichier copi�
        $this->goBack($name);
    }
}
?>