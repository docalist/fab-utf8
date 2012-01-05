<?php
/**
 * @package     fab
 * @subpackage  Admin
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Admin.php 1144 2010-04-08 12:43:31Z daniel.menard.bdsp $
 */

/**
 * Module d'administration de l'application.
 *
 * Le module Admin constitue le
 * {@link http://fr.wikipedia.org/wiki/Back_office_(informatique) 'BackOffice'}
 * de l'application : il ne comporte {@link actionIndex() qu'une seule action}
 * qui correspond � la page d'accueil du site d'administration de l'application
 * et qui affiche la liste des modules d'administration disponibles.
 *
 * La classe Admin est �galement la classe anc�tre de tous les autres modules
 * d'administration disponibles.
 *
 * Cliquez ici pour acc�der au {@link /Admin site d'administration de l'application}.
 *
 * @package     fab
 * @subpackage  Admin
 */
class Admin extends Module
{
    /**
     * Retourne le titre du module d'administration.
     *
     * Cette m�thode est appell�e automatiquement par la
     * {@link Admin::actionIndex() page d'accueil} du
     * {@link /Admin site d'administration} pour afficher la liste des modules
     * d'administration disponibles.
     *
     * Elle retourne le titre indiqu� dans la cl�
     * <code><title></code> de la configuration.
     *
     * Si aucun titre n'est indiqu�, elle retourne le nom de la classe en cours.
     *
     * Cette m�thode peut �tre surcharg�e par les modules d'administration
     * descendants de cette classe si une impl�mentation diff�rente est
     * souhait�e.
     *
     * @return string
     */
    public function getTitle()
    {
        $title=Config::get('title');
        if ($title) return $title;
        return get_class($this);
    }


    /**
     * Retourne la description du module d'administration.
     *
     * Cette m�thode est appell�e automatiquement par la
     * {@link Admin::actionIndex() page d'accueil} du
     * {@link /Admin site d'administration} pour afficher la liste des modules
     * d'administration disponibles.
     *
     * Le but est d'indiquer � l'utilisateur, en quelques lignes, le r�le
     * de chacun des modules d'administration.
     *
     * Par d�faut, <code>getDescription()</code> retourne le contenu de la cl�
     * <code><description></code> de la configuration ou null si aucune
     * description n'est disponible.
     *
     * Cette m�thode peut �tre surcharg�e par les modules d'administration
     * descendants de cette classe pour g�n�rer une description sp�cifique.
     *
     * @return null|string
     */
    public function getDescription()
    {
        return Config::get('description');
    }


    /**
     * Retourne l'url du logo � afficher pour ce module d'administration.
     *
     * Cette m�thode est appell�e automatiquement par la
     * {@link Admin::actionIndex() page d'accueil} du
     * {@link /Admin site d'administration} pour afficher la liste des modules
     * d'administration disponibles.
     *
     * La m�thode r�cup�re le nom de l'image indiqu� dans la cl�
     * <code><icon></code> du fichier de configuration.
     *
     * S'il s'agit d'un chemin relatif, celui-ci est transform� en chemin de la
     * forme <code>/FabWeb/modules/<nom du module>/images/<nom du logo></code>.
     *
     * Si aucun logo n'est indiqu� dans la configuration, la m�thode
     * retourne null.
     *
     * @return null|string
     */
    public function getIcon()
    {
        $icon=Config::get('icon');
        if ($icon && Utils::isRelativePath($icon)) // fixme: ne pas faire �a ici, int�grer dans le routing
            $icon='/FabWeb/modules/' . __CLASS__ . '/images/' . $icon;

        // fixme: ne marche pas si le module d'administration fait partie de l'application

        return $icon;
    }

    /**
     * Retourne un tableau permettant de construire un fil d'ariane
     * (breadcrumbs).
     *
     * Les cl�s du tableau retourn� contiennent les liens (non rout�s) des
     * diff�rentes �l�ments composant le fil d'ariane. Les valeurs associ�es
     * contiennent le libell� � afficher � l'utilisateur.
     *
     * @return array
     * @see getBreadCrumbs()
     */
    protected function getBreadCrumbsArray()
    {
        if (get_class($this)===__CLASS__)
            return array
            (
                '/Admin' => 'Administration',
            );

        return array
        (
            '/Admin' => 'Administration',
            'index' => $this->getTitle()
        );
    }

    /**
     * Retourne un tableau permettant de construire un fil d'ariane
     * (breadcrumbs).
     *
     * Exemple : Administration � Gestion des fichiers � Modules
     *
     * La m�thode retourne le m�me tableau que {@link getBreadCrumbsArray()}
     * si ce n'est que le dernier �l�ment n'a pas de lien (la cl� correspondante
     * est 0).
     *
     * @return array
     */
    final public function getBreadCrumbs()
    {
        $breadCrumbs=$this->getBreadCrumbsArray();
        array_push($breadCrumbs, array_pop($breadCrumbs));
        return $breadCrumbs;
    }

    /**
     * Affiche la liste des modules d'administration disponibles.
     *
     * Cette action charge chacun des modules indiqu�s dans la cl�
     * <code><modules></code> du fichier de configuration et construit un tableau
     * qui pour chacun des modules trouv�s indique :
     *
     * - <code>title</code> : le titre du module d'administration tel que
     *   retourn� par la m�thode {@link getTitle()} de ce module ;
     * - <code>description</code> : la description telle que retourn� par la
     *   m�thode {@link getDescription()} de ce module;
     * - <code>icon</code> : l'url de l'icone � afficher pour ce module, telle
     *   que retourn�e par la m�thode {@link getIcon()} de ce module ;
     * - <code>link</code> : l'url de l'action index de ce module.
     *
     * Elle appelle ensuite le template indiqu� dans la cl�
     * <code><template></code> du fichier de configuration en lui passant en
     * param�tre une variable <code>modules</code> contenant le tableau obtenu.
     *
     * @throws LogicException Une exception est g�n�r�e si la configuration
     * indique des modules qui ne sont pas des modules d'administration,
     * c'est-�-dire des modules qui ne descendent pas de la class
     * <code>Admin</code>.
     */
    public function actionIndex()
    {
        // D�termine le path du template qui sera ex�cut�
        // fixme: on est oblig� de le faire ici, car on charge un peu plus
        // bas d'autres modules qui vont �craser notre config et notre searchPath
        // et du coup, notre template ne sera plus trouv� lors de l'appel
        // � Template::run
        $template=Utils::searchFile(Config::get('template'));

        // sauvegarde notre config
        $config=Config::getAll();

        // Cr�e la requ�te utilis�e pour charger chacun des modules d'admin
        $request=Request::create()->setAction('');

        // Cr�e un tableau indiquant pour chacun des modules indiqu�s dans la
        // config : son titre, sa description, l'url de son icone et l'url
        // vers son action index
        $modules=array();

        foreach (Config::get('modules') as $moduleName=>$options)
        {
            // Charge le module indiqu�
            $module=Module::getModuleFor($request->setModule($moduleName));

            // V�rifie que c'est bien un module d'administration
            if (! $module instanceOf Admin)
                throw new LogicException("Le module $moduleName indiqu� dans la config n'est pas un module d'administration.");

            // Ajoute le module dans le tableau
            $modules[$moduleName]=array
            (
                'title'=>$module->getTitle(),
                'description'=>$module->getDescription(),
                'icon'=>$module->getIcon(),
                'link'=>$request->getUrl(),
            );
        }

        // Restaure notre config
        Config::clear();
        Config::addArray($config);

        // Ex�cute le template
        return Response::create('Html')->setTemplate
        (
            $this,
            $template,
            array('modules'=>$modules)

        );
    }
}
?>