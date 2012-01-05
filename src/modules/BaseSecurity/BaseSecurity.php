<?php

/**
 * Cette classe d�finit l'interface de base pour les modules de s�curit�.
 *
 * Tous les modules de s�curit� doivent h�riter de cette classe de base.
 *
 * Remarque : un module de s�curit� est aussi un module classique qui peut
 * avoir des actions. Cela permet de g�rer la s�curit� (hasAccess, etc.) au
 * m�me endroit que les actions (afficher le formulaire de connexion, access
 * denied, etc.)
 *
 * Cette classe de base impl�mente �galement un mod�le de s�curit� trivial.
 *
 * L'utilisateur :
 *
 * - est toujours anonyme
 *
 * - est toujours connect� (isConnected retourne toujours true)
 *
 * - a tous les droits (hasRight retourne toujours true)
 */
class BaseSecurity extends Module
{
    /**
     * @var string Les droits de l'utilisateur.
     * exemple : "AdminBdsp, EditWebs"
     *
     * (on peut utiliser n'importe quelle suite de caract�res comme s�parateur,
     * sauf les lettres, les chiffres, le soulign� et le tiret)
     */
    public $rights;

    /**
     * Teste si l'utilisateur est connect� (authentifi�)
     *
     * @return boolean true si l'utilisateur est connect�, false s'il
     * s'agit d'un visiteur anonyme
     */
    public function isConnected()
    {
        return true;
    }

    /**
     * V�rifie que l'utilisateur est connect� et l'envoie sur la page de
     * connexion si ce n'est pas le cas.
     */
    public function checkConnected()
    {
        if (! $this->isConnected())
            $this->logon();
    }

    /**
     * Force la connexion d'un utilisateur en le redirigeant vers
     * le formulaire de saisie du login/mot de passe
     */
    public function actionLogon()
    {
// TODO: � �tudier
// redirection vers l'url de connexion ?
// affichage direct du formulaire indiqu� dans la config puis die() ?
// appelle de l'action showLoginForm d'un module ?
//        $template=Config::get('user.loginform');  // plut�t l'url vers laquelle il faut aller ?
//        Routing::redirect()
    }

    /**
     * Teste si l'utilisateur dispose du droit unique indiqu�.
     * Contrairement � {@link hasAccess()}, hasRight() ne permet de tester qu'un
     * droit unique et non pas une combinaison de droits s�par�s par des
     * virgules et des plus.
     *
     * @param string $right le droit � tester
     * @return boolean true si l'utilisateur dispose du droit demand�, false
     * sinon.
     */
    public function hasRight($right) // TODO: est-ce que ce code (ou une partie) ne devrait pas �tre plut�t dans User?
    {
        // Tout le monde dispose du droit 'default'
        if (strcasecmp($right,'default')==0) return true;

        // Le droit 'cli' n'est accord� que si php tourne en ligne de commande
        if (strcasecmp($right,'cli')==0) return php_sapi_name()=='cli';

        // Extrait le r�le et l'objet du droit (on coupe � la seconde majuscule)
        $i=strcspn($right,'ABCDEFGHIJKLMNOPQRSTUVWXYZ',1);
        $role=substr($right, 0, $i+1);
        $object=substr($right, $i+1);

        // Construit l'expression r�guli�re utilis�e
        $re="~\\b(?:(?:$right)";
        if ($role && $role!=$right) $re.="|(?:$role)";
        if ($object) $re.="|(?:$object)";
        $re.=")\\b~";

        // Retourne vrai si on trouve soit le droit, soit le r�le, soit l'objet dans les droits
        return preg_match($re, $this->rights) != 0;
    }

    /**
     * Teste si l'utilisateur dispose des droits indiqu�s.
     *
     * @param string $level le ou les droit(s) � tester
     * @return boolean true si l'utilisateur dispose du droit requis,
     * false sinon
     */
    public function hasAccess($rights) // TODO: est-ce que ce code ne devrait pas �tre plut�t dans User?
    {
        if (trim($rights)=='') return true;
        foreach(explode(',', $rights) as $right) // ensemble s�par�s par des ','
        {
        	foreach(explode('+',trim($right)) as $right) // ensemble s�par� par des '+'
            	if (! $this->hasRight(trim($right)))
                    continue 2;
            return true;
        }
        return false;
    }

    /**
     * V�rifie que l'utilisateur dispose des droits indiqu�s et g�n�re une
     * erreur 'access denied' sinon.
     *
     * @param string $level le droit � tester
     */
    public function checkAccess($rights)
    {
        if (! $this->hasAccess($rights))
            $this->accessDenied();
    }


    /**
     * G�n�re une erreur 'access denied'
     */
    public function accessDenied()
    {
    	throw new Exception('Acc�s refus�');
    }

    /**
     * Accorde des droits suppl�mentaire � l'utilisateur.
     *
     * @param string $rights les droits � accorder
     */
    public function grantAccess($rights)
    {
        if ($this->rights)
            $this->rights .= ',' . $rights;
        else
            $this->rights .= $rights;
    }

}
?>
