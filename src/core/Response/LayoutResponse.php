<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Repr�sente une r�ponse dot�e d'un layout.
 *
 * (patterns two step view, composite view, etc.)
 *
 * @package     fab
 * @subpackage  response
 */
class LayoutResponse extends Response
{
    /**
     * Retourne le path du layout utilis� pour cette r�ponse.
     *
     * Le layout utilis� est d�finit dans la config (cl�s theme et layout).
     *
     * @return string le chemin complet du template � utiliser comme layout ou <code>false</code>
     * si aucun layout n'a �t� d�finit dans la configuration.
     */
    protected function getLayout()
    {
        // D�termine le th�me et le layout � utiliser
        $theme='themes' . DIRECTORY_SEPARATOR . Config::get('theme') . DIRECTORY_SEPARATOR;
        $defaultTheme='themes' . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR;
        $layout=Config::get('layout');

        if (strcasecmp($layout,'none')==0) return false;

        $path=Utils::searchFile
        (
            $layout,                                // On recherche le layout :
            Runtime::$root.$theme,                  // Th�me en cours, dans l'application
            Runtime::$fabRoot.$theme,               // Th�me en cours, dans le framework
            Runtime::$root.$defaultTheme,           // Th�me par d�faut, dans l'application
            Runtime::$fabRoot.$defaultTheme         // Th�me par d�faut, dans le framework
        );

        if (!$path)
            throw new Exception("Impossible de trouver le layout $layout");

        return $path;
    }


    /**
     * Ex�cute le layout et envoie le r�sultat sur la sortie standard.
     *
     * Dans notre cas (LayoutResponse), la m�thode travaille en collaboration avec la m�thode
     * runAction() de Module. La m�thode outputLayout() se contente en fait d'envoyer le layout.
     *
     * Celui-ci, lors de son ex�cution, va appeller Module::runAction() qui (c'est l� qu'est la
     * collaboration) va appeller la m�thode outputContent() pour afficher le contenu utile de la
     * r�ponse.
     *
     * @param object $context le contexte d'ex�cution du template utilis� comme layout (typiquement
     * le module qui a ex�cut� la requ�te).
     *
     * @return LayoutResponse $this
     */
    public function outputLayout($context)
    {
        $this->outputHeaders();

        if (false === ($layout = $this->getLayout()))
            return $this->outputContent();

        Template::runInternal($layout, array(array('this'=>$context)));

        return $this;
    }
}