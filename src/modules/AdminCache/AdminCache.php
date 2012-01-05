<?php
/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: AdminCache.php 792 2008-06-19 15:04:50Z daniel.menard.bdsp $
 */

/**
 * Module d'administration du cache.
 * 
 * Ce module d'administration permet de visualiser le 
 * {@link /AdminCache contenu du cache} et de supprimer tout ou partie des 
 * fichiers qu'il contient.
 * 
 * AdminCache h�rite de {@link AdminFiles}. Aucune m�thode n'est
 * introduite par ce module, seules quelques m�thodes h�rit�es et quelques
 * templates sont modifi�s.
 * 
 * Consultez la {@link Cache documentation de la classe Cache} pour plus 
 * d'informations sur les fichiers que fab met en cache.
 * 
 * @package     fab
 * @subpackage  Admin
 */
class AdminCache extends AdminFiles
{
    /**
     * Retourne le path complet du r�pertoire de travail de AdminCache.
     * 
     * Le path retourn� est construit � partir des �lements suivants :
     * - le r�pertoire racine contenant le cache de l'application,
     * - le r�pertoire �ventuel indiqu� dans le param�tre <code>directory</code>
     *   indiqu� en query string.
     * 
     * Une exception est g�n�r�e si le r�pertoire obtenu n'existe pas ou ne
     * d�signe pas un r�pertoire.
     * 
     * Le chemin retourn� contient toujours un slash final.
     *
     * @throws Exception est g�n�r�e si :
     * - le r�pertoire obtenu contient des s�quences de la forme 
     *   <code>/../</code> ;
     * - le r�pertoire obtenu n'existe pas ou d�signe autre chose qu'un 
     *   r�pertoire.
     *  
     * @return string le path obtenu.
     */
    public function getDirectory()
    {
        // D�termine le path du cache
        $path=Utils::makePath
        (
            realpath(Cache::getPath(Runtime::$root).'../..'), // On ne travaille que dans le cache de cette application 
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
     * D�termine l'icone � afficher pour repr�senter le type du fichier ou du
     * dossier pass� en param�tre.
     *  
     * Cette m�thode surcharge {@link AdminFiles::getFileIcon()} car les 
     * fichiers pr�sents dans le cache de l'application sont tous des fichiers
     * php, m�me s'ils ont une extension diff�rente.
     *
     * @param string $path
     * @return string une url de la forme 
     * <code>/FawWeb/modules/AdminFiles/images/filetypes/icon.png</code>
     */
    public function getFileIcon($path)
    {
        if (is_dir($path)) 
            return parent::getFileIcon($path);
        return parent::getFileIcon($path.'.php');
    }
    
    /**
     * D�termine le type de coloration syntaxique � appliquer aux fichier 
     * du cache.
     *
     * {@inheritdoc}
     * 
     * Comme les fichiers pr�sents dans le cache de l'application sont tous des 
     * fichiers (m�me s'ils ont une extension diff�rente), cette version de
     * <code>getEditorSyntax</code> retourne toujours la syntaxe 
     * <code>php</code>.
     *   
     * @param string $file le nom ou le path d'un fichier.
     * 
     * @return string la chaine <code>'php'</code>
     */
    public function getEditorSyntax($file)
    {
        return 'php';
    }
}

?>