<?php
/**
 * @package     fab
 * @subpackage  security
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */


/**
 * Module de s�curit� bas� sur un fichier texte contenant la liste des
 * utilisateurs du site.
 *
 * Ce module utilise le fichier <code>FileBasedSecurity.config</code> pour toutes ses options de
 * configuration : le fichier de Fab fournit des valeurs par d�faut et celui qui est d�finit dans
 * l'application permet de changer ces valeurs par d�faut.
 *
 * Exemple de fichier FileBasedSecurity.config :
 * <code>
 *     <file>
 *         <!--
 *             Path du fichier contenant la liste des utilisateurs.
 *
 *             Il peut s'agit d'un chemin "absolu" (commen�ant par un slash). Dans ce cas,
 *             le fichier est recherch� par rapport � la racine de l'application. Si c'est un
 *             chemin relatif, il est recherch� dans le r�pertoire /data de l'application
 *             (exemple : users/list.txt -> /data/users/list.txt).
 *
 *             L'application doit explicitement cr�er un fichier FileBasedSecurity.config
 *             dans son r�pertoire /config et indiquer le path du fichier utilis�.
 *          -->
 *         <path>/data/users.txt</path>
 *
 *         <!--
 *             Param�tres du fichier CSV : s�parateur de champs (virgule par d�fault),
 *             caract�re de d�limitation (guillemet par d�faut).
 *             Consulter la fonction {@link http://php.net/fgetcsv} pour plus d'infos.
 *         -->
 *         <delimiter>,</delimiter>
 *         <enclosure>"</enclosure>
 *     </file>
 *
 *     <!--
 *         Param�tres du cookie.
 *
 *         Consulter {@link http://php.net/setcookie} pour plus d'informations sur les diff�rentes options.
 *     -->
 *     <cookie>
 *          <!-- nom du cookie g�n�r� ('user' par d�faut) -->
 *         <name>user</name>
 *
 *          <!--
 *             Dur�e de vie, en secondes du cookie g�n�r� (10 jours par d�faut).
 *             Si l'utilisateur coche la case "se souvenir de moi", le cookie g�n�r� sera valide
 *             pour la dur�e indiqu�e ci-dessous, sinon, lifetime est ignor� et le cookie est
 *             effac� d�s que l'utilisateur ferme son navigateur (et au maximum 24h).
 *         -->
 *         <lifetime>864000</lifetime>
 *
 *          <!-- path du cookie ('/' par d�faut) -->
 *         <path>/</path>
 *
 *          <!-- domaine du cookie (null par d�faut) -->
 *         <domain />
 *
 *          <!-- http-only (true par d�faut) -->
 *         <http-only>true</http-only>
 *     </cookie>
 * </code>
 *
 * La liste des utilisateurs du site est g�r�e dans un fichier texte au format
 * {@link http://fr.wikipedia.org/wiki/Comma-separated_values CSV} avec une ligne d'ent�te
 * indiquant le nom des diff�rentes colonnes.
 *
 * Le chemin du fichier (<code><file.path></code>) et le format utilis� sont indiqu�s dans le
 * fichier de configuration (s�parateur de colonnes, caract�res d'�chappement, etc.)
 *
 * Le fichier CSV DOIT contenir les colonnes "login", "password" et "rights" et il PEUT contenir
 * des colonnes suppl�mentaires (nom, pr�nom, e-mail, etc.) qui seront charg�es automatiquement
 * et deviendront des propri�t�s publiques de l'utilisateur en cours
 * (exemple d'utilisation : <code>User::get('pr�nom')</code>).
 *
 * Remarques :
 * - L'ordre des colonnes dans le fichier n'a pas d'importance.
 * - Les espaces de d�but et de fin de colonne sont ignor�s.
 * - Les lignes vides sont ignor�es.
 *
 * La colonne "rights" doit indiquer les permissions dont dispose l'utilisateur sous la forme
 * d'une liste de droits s�par�s par une virgule (utiliser des guillemets si la virgule est
 * utilis�e comme s�parateur de colonnes) : par exemple <code>"AdminBase, EditContacts"</code>.
 *
 * Exemple :
 * <code>
 * login, password, rights, firstname, surname, mail
 * dmenard, abcd123, AdminFab, Daniel, M�nard, daniel.menard@ehesp.fr
 * ferron, efgh456, "AdminBase,AdminEmploi", S�verine, Ferron, severine.ferron@ehesp.fr
 * test, "ijkl 789",,"compte de test, aucun droits",,
 * </code>
 *
 * Pour se connecter, l'utilisateur appelle l'action {@link actionLogin() Login} et saisit son
 * identifiant (login) et son mot de passe. Il peut aussi activer l'option "se souvenir de moi"
 * pour �viter d'avoir � se reconnecter � chacune de ses visites.
 *
 * Lorsqu'il valide ses informations, le login et le mot de passe sont v�rifi�s : ils doivent
 * correspondre � un utilisateur existant dans le fichier et la comparaison tient compte de la
 * casse des caract�res. Si les codes sont invalides, le formulaire est r�affich� avec un message
 * d'erreur. Sinon, un cookie est g�n�r� et l'utilisateur est connect� au site.
 *
 * Les param�tres du cookie g�n�r� sont d�finis dans le fichier de configuration (nom du cookie,
 * path, domaine, etc.)
 *
 * La valeur du cookie est crypt�e avec un double m�canisme d'encodage utilisant les fonctions
 * {@link http://php.net/hash_hmac hash_hmac()} et {@link http://php.net/md5 md5()} et utiliser
 * une cl� (salt) qui d�pend � la fois du login et du mot de passe de l'utilisateur.
 *
 * Par d�faut, le cookie g�n�r� n'est valable que pour la dur�e de la visite de l'utilisateur
 * (le cookie est effac� d�s que le navigateur est ferm�) avec une dur�e de vie maximale de 24
 * heures (si le navigateur reste ouvert plus de 24 heures, l'utilisateur devra se reconnecter).
 *
 * Si l'utilisateur a coch� l'option "Se souvenir de moi", le cookie g�n�r� est valable tant que
 * la dur�e indiqu�e dans la cl� <code><cookie.lifetime></code> n'est pas �coul�e.
 *
 * Enfin, le module dispose d'une action {@link actionLogout() Logout} qui permet � l'utilisateur
 * de se d�connecter et d'effacer de son navigateur le cookie g�n�r� lors de la connexion.
 *
 * Utilisation du module :
 *
 * Pour utiliser le module, commencez par cr�er dans le r�pertoire <code>/config</code> de
 * l'application un fichier <code>FileBasedSecurity.config</code> et un fichier CSV contenant
 * la liste des utilisateurs du site (cf mod�les ci-dessus).
 *
 * Il suffit ensuite d'indiquer � Fab que c'est le module FileBasedScurity qui est utilis� pour
 * g�rer la s�curit� du site en ajoutant le code suivant dans le fichier
 * <code>general.config</code> de l'application :
 *
 * <code>
 * <security>
 *     <handler>FileBasedSecurity</handler>
 * </security>
 * </code>
 *
 * @package     fab
 * @subpackage  security
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 */
class FileBasedSecurity extends BaseSecurity
{
    /**
     * Valide le login et le mot de passe saisis par l'utilisateur dans le formulaire g�n�r�
     * par {@link actionLogin()}, connecte l'utilisateur si ceux-ci sont corrects puis le redirige
     * vers une autre page.
     *
     * On fait ce travail dans preExecute() plut�t que dans {@link actionLogin()} car pour
     * faire la redirection, on ne veut avoir ni th�me, ni layout (on pourrait sinon obtenir
     * une erreur "headers already sent").
     *
     * @return bool <code>true</code> si l'utilisateur a �t� connect�, <code>false</code> sinon.
     */
    public function preExecute()
    {
        if ($this->method === 'actionLogin')
        {
            // Connecte l'utilisateur si les donn�es du formulaire sont correctes
            if
            (
                Utils::isPost()                             // c'est une requ�te POST
                && isset($_COOKIE['test'])                  // le navigateur supporte les cookies
                && isset($_POST['login'])                   // on a un login
                && isset($_POST['password'])                // on a un mot de passe
                && $user = $this->getUser($_POST['login'], $_POST['password']) // ils sont corrects
            )
            {
                // Efface le cookie de test (cf plus bas)
                setcookie('test', 1);

                // Stocke le cookie de connexion
                $this->setCookie($user);

                // D�termine l'url de la page vers laquelle on va rediriger l'utilisateur
                if (isset($_POST['redirect']))
                    $url = Routing::linkFor($_POST['redirect'], true);
                else
                    $url = Routing::linkFor('/', true);

                // Redirige l'utilisateur
                Runtime::redirect($url);

                // Indique � fab qu'on a fait le boulot et qu'il ne faut pas appeller actionLogin()
                return true;
            }

            // Sinon, g�n�re un cookie de test pour v�rifier que le navigateur supporte les cookies
            setcookie('test', 1);

            // Laisse actionLogin() afficher le formulaire et les erreurs �ventuelles
        }
    }


    /**
     * Affiche un formulaire permettant � l'utilisateur de saisir ses codes d'acc�s (identifiant
     * et mot de passe).
     *
     * La m�thode utilise le template <code>login.html</code>.
     *
     * @param string $login identifiant de l'utilisateur.
     * @param string $password mot de passe de l'utilisateur.
     * @param bool $remember 1 si l'utilisateur a coch� l'option "se souvenir de moi", 0 sinon.
     * @param string $redirect url de la page vers laquelle sera redirig� l'utilisateur s'il
     * parvient � se connecter.
     */
    public function actionLogin($login='', $password='', $remember=0, $redirect='/')
    {
        // Si on nous a transmis des param�tres, on v�rifie le login et le mot de passe.
        // Le travail a d�j� �t� fait en partie dans {@link preExecute()}, on refait une partie
        // du boulot ici juste pour pouvoir afficher un message d'erreur correct � l'utilisateur.
        $error = false;
        if (Utils::isPost())
        {
            // Le navigateur ne support pas les cookies
            if (! isset($_COOKIE['test']))
                $error = 'Votre navigateur ne supporte pas les cookies. Vous ne pourrez pas vous connecter au site.';

            // Pas de login
            elseif ($login == '')
                $error = "Vous n'avez pas indiqu� l'identifiant.";

            // Pas de password
            elseif($password == '')
                $error = "Vous n'avez pas indiqu� le mot de passe.";

            // On a les deux
            else
                $error = 'Identifiant ou mot de passe invalides.';
        }

        // Affiche le formulaire de connexion au site
        Template::run
        (
            'login.html',
            array
            (
                'login'    => $login,
                'password' => $password,
                'remember' => $remember,
                'redirect' => $redirect,
                'error'    => $error,
            )
        );
    }


    /**
     * D�connecte l'utilisateur en cours et le redirige vers l'url pass�e en param�tre.
     *
     * @param string $redirect url de la page vers laquelle sera redirig� l'utilisateur apr�s avoir
     * �t� d�connect�.
     */
    public function actionLogout($redirect='/')
    {
        $this->clearCookie();
        Runtime::redirect($redirect);
    }


    /**
     * Charge la liste des utilisateurs du site � partir du fichier.
     *
     * La m�thode charge le fichier indiqu� dans la cl� <code><security.file></code> du fichier
     * de configuration et construit un tableau index� par login d'utilisateur.
     *
     * Le path du fichier indiqu� est soit un chemin "absolu" (commen�ant par un slash) par rapport
     * � la racine de l'application (par exemple <code>/private/users.txt</code>), soit un chemin
     * relatif au r�pertoire <code>/data</code> de l'application
     * (exemple : <code>users/list.txt -> /data/users/list.txt</code>).
     *
     * @return array un tableau contenant tous les utilisateurs d�finits dans le fichier.
     */
    private function loadUsers()
    {
        // V�rifie qu'un fichier de login/mot de passe a �t� fourni
        if (is_null($path=$this->config['file']['path']))
            throw new Exception(__CLASS__ . ' : aucun fichier d\'utilisateurs n\'a �t� indiqu� dans la cl� security.file du fichier de configuration.');

        // D�termine le path exact du fichier
        if (Utils::isRelativePath($path))
            $path=Utils::makePath(Runtime::$root, 'data', $path);
        else
            $path=Utils::makePath(Runtime::$root, $path);

        // V�rifie que le fichier indiqu� existe
        if (! file_exists($path))
            throw new Exception(__CLASS__ . ' : impossible de trouver le fichier d\'utilisateurs indiqu� dans la cl� security.file du fichier de configuration.');

        // R�cup�re les param�tres du fichier
        $delimiter = $this->config['file']['delimiter'];
        $enclosure = $this->config['file']['enclosure'];

        // Ouvre le fichier
        if (!is_resource($file = fopen($path, 'r')))
            throw new Exception(__CLASS__ . ' : impossible de charger le fichier d\'utilisateurs indiqu� dans la cl� security.file du fichier de configuration.');

        // Charge la ligne d'ent�te, ignore les espaces et v�rifie qu'elle est correcte
        $header = fgetcsv($file, 1000, $delimiter, $enclosure);
        $header = array_map('trim', $header);
        if (! in_array('login', $header) || ! in_array('password', $header))
            throw new Exception(__CLASS__ . ' : le fichier utilisateurs est incorrect, il doit contenir les colonnes "login", "password" et "rights".');

        // Charge tous les utilisateurs
        $users = array();
        $line=1;
        while (false !== $user = fgetcsv($file, 1000, $delimiter, $enclosure))
        {
            // Passe les lignes vides
            ++$line;
            if (is_null($user[0])) continue;

            //
            if (count($user) != count($header))
                throw new Exception(__CLASS__ . ' : erreur ligne ' . $line . ' du fichier d\'utilisateurs, nombre de colonnes incorrect.');

            // Supprime les espaces de d�but et de fin de colonne
            $user = array_map('trim', $user);

            // Cr�e un tableau index� par nom de champ
            $user = array_combine($header, $user);
            $users[$user['login']] = (object) $user;

        }

        // Ferme le fichier
        fclose($file);

        // Ok, termin�
        return $users;
    }


    /**
     * V�rifie que l'identifiant et le mot de passe pass�s en param�tre sont valides
     * et retourne l'objet User correspondant.
     *
     * @param string $login l'identifiant de l'utilisateur (login)
     * @param string $password le mot de passe de l'utilisateur.
     * @return object un objet contenant les propri�t�s de l'utilisateur.
     */
    private function getUser($login, $password)
    {
        // Charge la liste des utilisateurs
        $users = $this->loadUsers();

        // V�rifie que le login indiqu� existe
        if (! isset($users[$login]))
            return false;

        // Charge l'utilisateur
        $user = $users[$login];

        // V�rifie que le mot de passe est correct
        if ($user->password !== $password)
            return false;

        // Retourne l'utilisateur
        return $user;
    }


    /**
     * D�finit le cookie utilisateur en cryptant sa valeur.
     *
     * @param object $user un objet utilisateur tel que retourn� par {@link getUser()}.
     * @param bool $remember <code>true</code> pour g�n�rer un cookie persistant, <code>false</code>
     * pour g�n�rer un cookie de session.
     */
    private function setCookie($user, $remember=false)
    {
        $cookie = $this->config['cookie'];

        // Calcule la date d'expiration du cookie
        if ($remember)
        {
            // expire apr�s la dur�e indiqu�e dans la config
            $validUntil = $expire = time() + $cookie['lifetime'];
        }
        else
        {
            // cookie valide jusqu'� ce que le navigateur doit ferm�
            $expire = 0;

            // mais pas si le navigateur reste ouvert plus de 24h
            $validUntil = time() + 24*60*60;
        }

        // Calcule et crypte la valeur du cookie
        $key = md5($user->login . $user->password . '|' . $validUntil);
        $hash = hash_hmac('md5', $user->login . '|' . $validUntil, $key);
        $value = sprintf('%s|%d|%s', $user->login, $validUntil, $hash);

        // Le hash pr�sent dans le cookie r�sulte d'une double encodage.
        // D'abord, on calcule une cl� de cryptage bas�e sur le login de l'utilisateur
        // et la date d'expiration du cookie, ensuite on encode � nouveau le login
        // et la date d'expiration en utilisant cette cl�.

        // Cr�e le cookie
        setcookie
        (
            $cookie['name'],
            $value,
            $expire,
            $cookie['path'],
            $cookie['domain'],
            false,
            $cookie['http-only']
        );
        $_COOKIE[$cookie['name']]=$value;
    }


    /**
     * Efface le cookie utilisateur g�n�r� par un appel pr�c�dent �
     * {@link setCookie()}.
     */
    private function clearCookie()
    {
        $cookie = $this->config['cookie'];

        // Efface le cookie
        setcookie
        (
            $cookie['name'],
            false,
            0,
            $cookie['path'],
            $cookie['domain'],
            false,
            $cookie['http-only']
        );
        unset($_COOKIE[$cookie['name']]);
    }


    /**
     * Teste si l'utilisateur dispose d'un cookie de connexion valide et, si c'est le cas,
     * le connecte au site.
     *
     * @return bool <code>true</code> si l'utilisateur a un cookie valide et qu'il a �t� connect�,
     * <false> sinon.
     */
    private function checkCookie()
    {
        // R�cup�re le nom du cookie
        $name = $this->config['cookie']['name'];
        if (isset($_COOKIE[$name]))
        {
            $cookie = $_COOKIE[$name];

            // R�cup�re le contenu du cookie : login|date d'expiration|hash
            $parts = explode('|', $_COOKIE[$name]);
            if ( count($parts) !== 3 ) return;
            list($login, $validUntil, $hash) = $parts;

            // Si le cookie a expir�, l'utilisateur n'est plus connect�
            if ( $validUntil < time() ) return;

            // Charge la liste des utilisateurs si ce n'est pas d�j� fait
            $users = $this->loadUsers();

            // V�rifie que l'utilisateur indiqu� existe
            if (! isset($users[$login])) return;
            $user = $users[$login];

            // V�rifie que le cookie n'a pas �t� trafiqu�.
            // Le hash pr�sent dans le cookie r�sulte d'une double encodage.
            // D'abord, on calcule une cl� de cryptage bas�e sur le login de l'utilisateur
            // et la date d'expiration du cookie, ensuite on encode � nouveau le login
            // et la date d'expiration en utilisant cette cl�.
            $key = md5($user->login . $user->password . '|' . $validUntil);
            $correctHash = hash_hmac('md5', $user->login . '|' . $validUntil, $key);

            // Si le hash obtenu est diff�rent du hash pr�sent dans le cookie, abandon.
            if ($hash != $correctHash ) return;

            foreach ($user as $property => $value)
                $this->$property = $value;

            return true;
        }
    }

    /**
     * Indique si l'utilisateur en cours est connect� (authentifi�)
     *
     * @return boolean true si l'utilisateur est connect�, false s'il
     * s'agit d'un visiteur anonyme
     */
    public function isConnected()
    {
        return isset($this->login);
    }


    /**
     * Essaie de connecter l'utilisateur au site si celui-ci a envoy�
     * un cookie de connexion valide.
     *
     * La m�thode <code>initialize()</code> est appell�e automatiquement par la m�thode
     * {@link Module::loadModule()} une fois que la configuration du module a �t� charg�e.
     */
    public function initialize()
    {
        parent::initialize();
        $this->checkCookie();
    }
}