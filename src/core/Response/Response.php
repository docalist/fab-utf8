<?php
/**
 * @package     fab
 * @subpackage  response
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Repr�sente la r�ponse g�n�r�e par une action.
 *
 * Le r�sultat d'une action est toujours un objet <code>Response</code> : Fab appelle l'action
 * demand�e en lui passant un objet {@link Request}, l'action s'ex�cute et collecte les donn�es
 * requises puis pr�pare et retourne un objet de type <code>Response</code>.
 *
 * Fab se charge alors d'ex�cuter la r�ponse obtenue en appellant sa m�thode {@link output()}.
 *
 * Il existe plusieurs type de r�ponses, chaque type �tant repr�sent� par un objet sp�cifique
 * descendant de la classe g�n�rique <code>Response</code> ({@link HtmlResponse},
 * {@link JsonResponse}, {@link RedirectResponse}, etc.)
 *
 * La classe <code>Response</code> permet de param�trer compl�tement la sortie envoy�e au client
 * (le navigateur en g�n�ral) en d�finissant le {@link setStatus() code http}, les
 * {@link setHeader() ent�tes http}, les {@link setCookie() cookies} et le contenu retourn�.
 *
 * Le contenu de la r�ponse peut �tre {@link setContent() statique} ou
 * {@link setTemplate() dynamique} (c'est-�-dire d�finit en utilisant un {@link Template template}).
 *
 * Remarque :
 * <code>Response</code> dispose d'une interface fluide : les m�thodes qui ne retournent pas de
 * valeur retournent <code>$this</code> pour permettre de chainer les appels. Pour simplifier
 * la cr�ation d'une requ�te, <code>Response</code> dispose d'une {@link create() m�thode
 * statique de fabrication} permettant de cr�er une r�ponse d'un type donn�.
 *
 * Exemple :
 * <code>
 * Response::create('html')->setStatus(200)->setContent('Hello World !')->output()
 * </code>
 *
 * @package     fab
 * @subpackage  response
 */
class Response
{
    /**
     * Version du protocole http utilis�e pour la r�ponse (<code>'1.0'</code> ou <code>'1.1'</code>).
     *
     * @var string
     */
    protected $version = null;


    /**
     * Code http de la r�ponse tel que d�finit par {@link setStatus()}.
     *
     * Par d�faut, la propri�t� est � <code>null</code> ce qui signifie qu'on n'envoie rien
     * nous-m�me et qu'on laisse l'action ex�cut�e, php et/ou apache fournir (ou non) une valeur
     * par d�faut.
     *
     * @var int
     */
    protected $status = null;


    /**
     * Ent�tes http g�n�r�s par la r�ponse tels que d�finits par {@link setHeader()}.
     *
     * @var array()
     */
    protected $headers = array();


    /**
     * {@link http://fr.wikipedia.org/wiki/Type_MIME Type mime} de la requ�te. Cette propri�t� est
     * destin�e � �tre surcharg�e par les classes descendantes. Le constructeur ajoute
     * automatiquement un ent�te http 'Content-Type' contenant le type mime indiqu�.
     *
     * Par d�faut, la propri�t� est � <code>null</code> ce qui signifie qu'on n'envoie rien
     * nous-m�me et qu'on laisse l'action ex�cut�e, php et/ou apache fournir (ou non) une valeur
     * par d�faut.
     *
     * @var string
     */
    protected $contentType = null;


    /**
     * Charset utilis� pour d�finir l'ent�te Content-Type.
     *
     * Cette propri�t� est destin�e � �tre surcharg�e par les classes descendantes. Son contenu
     * est automatiquement ajout� au contenu de la propri�t� {@link $contentType} pour former
     * l'ent�te http Content-Type.
     *
     * Par d�faut, la propri�t� est � <code>null</code> ce qui signifie qu'on n'envoie rien
     * nous-m�me et qu'on laisse l'action ex�cut�e, php et/ou apache fournir (ou non) une valeur
     * par d�faut.
     *
     * @var string
     */
    protected $charset = null;


    /**
     * Cookies g�n�r�s par la r�ponse (cf {@link cookie()}).
     *
     * Chaque �l�ment est un tableau contenant les arguments pass�s en param�tres lors de l'appel �
     * la m�thode {@link setCookie()}.
     *
     * @var array
     */
    protected $cookies = array();


    /**
     * Contenu statique de la r�ponse tel que d�finit par {@link setContent()}.
     *
     * @var mixed
     */
    protected $content = null;


    /**
     * Path du template � utiliser pour la r�ponse tel que d�finit par {@link setTemplate()}.
     *
     * @var string
     */
    protected $template = null;


    /**
     * Donn�es du template � utiliser pour la r�ponse telles que d�finies par {@link setTemplate()}.
     *
     * @var string
     */
    protected $templateData = null;


    /**
     * Liste des codes de statut http valides et texte associ�.
     *
     * Source : {@link http://fr.wikipedia.org/wiki/Liste_des_codes_HTTP}.
     *
     * @var array(string)
     */
    static protected $httpStatus = array
    (
        // 1xx : Information
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',          // WebDav

        // 2xx : Succ�s
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',        // WebDav
        210 => 'Content Different',   // WebDav

        // 3xx : Redirection
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Moved Temporarily',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',

        // 4xx : Erreur du client
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Unsatisfiable',
        417 => 'Expectation Failed',
     // 418 => "I'm a teapot"             // Blague de 1er avril : "Coffee Pot Transfer Protocol"
        422 => 'Unprocessable entity',    // WebDav
        423 => 'Locked',                  // WebDav
        424 => 'Method failure',          // WebDav
        425 => 'Unordered Collection',    // WebDav
     // 426 => 'Upgrade required',        // Sp�cifique : TLS
     // 449 => 'Retry with',              // Sp�cifique Microsoft
     // 450 => 'Blocked by Windows',      // sp�cifique Windows : contr�le parental

        // 5xx : Erreurs du serveur
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway or Proxy Error',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        507 => 'Insufficient storage', // WebDav
        509 => 'Bandwidth Limit Exceeded', // Non officiel : d�passement de quota
    );


    /**
     * Cr�e un objet Response.
     *
     * Si un type mime {@link $contentType a �t� d�fini}, ajoute une ent�te http Content-Type � la
     * r�ponse.
     */
    public function __construct()
    {
        if ($this->contentType)
        {
            if ($this->charset)
                $content = $this->contentType . ';charset=' . $this->charset;
            else
                $content = $this->contentType;

            $this->setHeader('Content-Type', $content);
        }
    }


    /**
     * Retourne le type de la r�ponse (Html, Text, Json, Redirect, etc.)
     *
     * Le type de la r�ponse est d�termin� � partir du nom de la classe : par convention,
     * c'est tout ce qui pr�c�de le mot 'Response' dans le nom de la classe.
     *
     * Exemple :
     * Pour un objet de type {@link JsonResponse}, <code>getType()</code>retourne
     * <code>'Json'</code>.
     *
     * @return string
     */
    public function getType()
    {
        return strtok(get_class($this), __CLASS__);
    }


    /**
     * Factory : cr�e un objet Response du type indiqu�.
     *
     * @param string $type le type de r�ponse � cr�er (Html, Text, Json, Redirect, etc.)
     * Si <code>$type</code> n'est pas indiqu�, une r�ponse de type g�n�rique est retourn�e.
     *
     * @param mixed $args ... les arguments � passer au constructeur de la r�ponse.
     *
     * @return Response $this
     */
    public static function create($type='', $args=null)
    {
        $args = func_get_args();
        array_shift($args);

        $class = new ReflectionClass(ucfirst(strtolower($type)) . 'Response');
        return $class->newInstanceArgs($args);
    }


    /**
     * D�finit le template et les donn�es � utiliser pour g�n�rer le contenu de la r�ponse.
     *
     * Si vous d�finissez � la fois un contenu statique (avec {@link setContent()}) et un contenu
     * dynamique (avec <code>setTemplate()</code>) la r�ponse g�n�r�e contiendra le contenu statique
     * puis le contenu du template.
     *
     * @param object $context le contexte dans lequel le template sera ex�cut� : lors de l'ex�cution
     * du template, l'objet pass� en param�tre sera accessible en utilisant <code>$this</code>.
     *
     * @param string $path le chemin (absolu ou relatif) du template � utiliser.
     *
     * @param mixed $data ... une liste variable d'arguments repr�sentant les donn�es du template
     *
     * @return Response $this
     */
    public function setTemplate($context, $path, $data=null)
    {
        // V�rifie $context
        if (! is_object($context))
            throw new InvalidArgumentException('Contexte invalide, objet attendu');

        // Stocke le chemin absolu du template
        if (Utils::isRelativePath($path) &&  false === $path=Utils::searchFile($sav=$path))
            throw new Exception("Impossible de trouver le template $sav.");
        $this->template = $path;

        // Stocke les donn�es du template
        $this->templateData = func_get_args();
        array_splice($this->templateData, 0, 2, array(array('this'=>$context)));

        return $this;
    }


    /**
     * D�finit le contenu statique � utiliser pour g�n�rer la r�ponse.
     *
     * Si vous d�finissez � la fois un contenu statique (avec <code>setContent()</code>) et un
     * contenu dynamique (avec {@link template()}) la r�ponse g�n�r�e contiendra le contenu
     * statique puis le contenu du template.
     *
     * @param mixed $content
     *
     * @return Response $this
     */
    public function setContent($content = null)
    {
        $this->content = $content;
        return $this;
    }


    /**
     * Ajoute un contenu avant le contenu existant.
     *
     * @param mixed $content
     *
     * @return Response $this
     */
    public function prependContent($content)
    {
        $this->content = $content . $this->content;
        return $this;
    }


    /**
     * Ajoute un contenu apr�s le contenu existant.
     *
     * @param mixed $content
     *
     * @return Response $this
     */
    public function appendContent($content)
    {
        $this->content .= $content;
        return $this;
    }


    /**
     * Retourne une description de l'objet en cours.
     *
     * @return string
     */
    public function __toString()
    {
        ob_start();

        // Class type
        printf("<pre>%s�:\n{\n", get_class($this));

        // Statut http
        if ($this->status)
        {
            if (is_null($this->version)) $this->setVersion();
            printf("    HTTP/%s %s %s\n", $this->version, $this->status, self::$httpStatus[$this->status]);
        }

        // Ent�tes http
        foreach ($this->headers as $name => $value)
            foreach((array)$value as $item)
                printf("    %s: %s\n", $name, $item);

        // Cookies
        foreach ($this->getCookies() as $cookie)
        {
            $name = isset($cookie['name']) ? $cookie['name'] : 'null';
            unset($cookie['name']);
            $value = isset($cookie['value']) ? $cookie['value'] : 'null';
            unset($cookie['value']);
            $properties = '';
            if ($cookie)
            {
                foreach($cookie as $k=>$v)
                    $properties[] = $k . '=' . $v;
                $properties = '(' . implode(',', $properties) . ')';
            }

            printf("    Set-Cookie: %s=%s%s\n", $name, $value, $properties);
        }

        print("\n");
        echo htmlspecialchars($this->render());

        printf("\n}</pre>");

        return ob_get_clean();
    }


    /**
     * D�finit le code http de la r�ponse.
     *
     * @param int $status un {@link http://fr.wikipedia.org/wiki/Liste_des_codes_HTTP code http valide}.
     *
     * @return Response $this
     *
     * @throw InvalidArgumentException si le code indiqu� n'est pas valide.
     */
    public function setStatus($status)
    {
        $status = (int) $status;
        if (! isset(self::$httpStatus))
            throw new InvalidArgumentException('Statut http invalide : ' . $status);
        $this->status = $status;
        return $this;
    }


    /**
     * Retourne le code http de la r�ponse.
     *
     * @return int le {@link http://fr.wikipedia.org/wiki/Liste_des_codes_HTTP code http}
     * actuellement d�finit pour la r�ponse.
     */
    public function getStatus()
    {
        return $this->status;
    }


    /**
     * D�finit la version du protocole http utilis�e pour la r�ponse.
     *
     * @param null|string $version la version du protocole http � utiliser. Si vous indiquez une
     * chaine de caract�res, seules les valeurs '1.0' et '1.1' sont autoris�es comme argument.
     *
     * Si vous n'indiquez aucun argument ou si $version vaut null, c'est la version actuellement
     * d�finie dans $_SERVER['SERVER_PROTOCOL'] qui est utilis�e.
     *
     * @return Response $this
     *
     * @throw InvalidArgumentException si la version indiqu�e n'est pas valide.
     */
    public function setVersion($version = null)
    {
        if (is_null($version))
        {
            strtok($_SERVER['SERVER_PROTOCOL'],'/');
            $version = strtok('/');
        }

        if ($version !== '1.0' && $version !== '1.1')
            throw new InvalidArgumentException('Version http invalide : ' . $version);

        $this->version = $version;

        return $this;
    }


    /**
     * Retourne la version du protocole http qui sera utilis�e pour g�n�rer la r�ponse.
     *
     * @return string la version du protocole http utilis�e ('1.0' ou '1.1').
     */
    public function getVersion()
    {
        return $this->status;
    }


    /**
     * Ajoute ou supprime un ent�te http de la r�ponse.
     *
     * La m�thode fonctionne comme la fonction {@link http://php.net/header header()} de php.
     *
     * @param string $name le nom de l'ent�te � ajouter.
     *
     * @param string|null $value la valeur de l'ent�te. Si $value vaut null, l'ent�te est supprim�
     * de la r�ponse.
     *
     * @param bool $replace indique ce qu'il faut faire si l'ent�te � ajouter existe d�j� dans la
     * r�ponse. Si $replace vaut true, l'ent�te existant est ecras�, sinon, la valeur indiqu�e est
     * ajout�e � l'ent�te existant.
     *
     * @return Response $this
     */
    public function setHeader($name, $value = null, $replace = true)
    {
        // Normalise le nom de l'ent�te
        $name = $this->headerName($name);

        // Supprime l'ent�te si $value est null
        if (is_null($value))
        {
            unset($this->headers[$name]);
            return $this;
        }

        // Stocke l'ent�te si replace est � true ou si l'ent�te n'existe pas d�j�
        if ($replace || ! isset($this->headers[$name]))
        {
            $this->headers[$name] = $value;
            return $this;
        }

        // Ajout d'une nouvelle valeur � un ent�te d�j� d�fini
        if (is_array($this->headers[$name]))
            $this->headers[$name][] = $value;
        else
            $this->headers[$name] = array($this->headers[$name], $value);

        return $this;
    }


    /**
     * Retourne la valeur actuelle de l'ent�te http indiqu� ou <code>false</code> si l'ent�te n'a
     * pas �t� d�fini.
     *
     * @param string $name
     * @return string|false
     */
    public function getHeader($name)
    {
        $name = $this->headerName($name);
        if (isset($this->headers[$name]))
            return $this->headers[$name];
        return false;
    }


    /**
     * Indique si la r�ponse contient l'ent�te http indiqu�.
     *
     * @param string $name nom de l'ent�te http � tester.
     * @return bool <code>true</code> si l'ent�te est d�finit dans la r�ponse avec une valeur
     * non nulle, <code>false</code> sinon.
     */
    public function hasHeader($name)
    {
        return isset($this->headers[$this->headerName($name)]);
    }


    /**
     * Retourne tous les ent�tes http actuellement d�finits pour la r�ponse.
     *
     * @return array un tableau contenant tous les ent�tes. Les cl�s du tableaux correspondent aux
     * noms des ent�tes ajout�s. Les valeurs correspondent aux valeurs pass�es en param�tre lors
     * de l'appel � la m�thode {@link header()}. Si un m�me ent�te a �t� d�finit plusieurs fois,
     * dans ce cas la valeur correspondante dans le tableau sera un tableau contenant les
     * diff�rentes valeurs.
     */
    public function getHeaders()
    {
        return $this->headers;
    }


    /**
     * Efface tous les ent�tes http actuellement d�finits pour la requ�te.
     *
     * @return Response $this
     */
    public function clearHeaders()
    {
        $this->headers = array();
        return $this;
    }


    /**
     * Valide et redresse le nom d'un ent�te http (exemple : 'content type' -> 'Content-Type').
     *
     * @param string $name
     * @return string
     */
    protected function headerName($name)
    {
        return strtr(ucwords(strtolower(strtr(trim($name), '-_', '  '))), ' ', '-');
    }


    /**
     * D�finit un cookie.
     *
     * La m�thode fonctionne comme la fonction {@link http://php.net/setrawcookie()} de php.
     *
     * @param string $name Le nom du cookie.
     * @param string $value La valeur du cookie
     * @param timestamp $expire La dur�e de vie du cookie (0 = � la fin de la session du navigateur)
     * @param string $path Le chemin sur le serveur sur lequel le cookie sera disponible.
     * @param string $domain Le domaine o� le cookie est disponible
     * @param bool $secure Lorsque $secure vaut <code>true</code>, le cookie n'est d�finit que
     * pour les connexions https.
     * @param bool $httpOnly Lorsque ce param�tre vaut <code>true</code>, le cookie ne sera
     * accessible que par le protocole HTTP
     *
     * @return Response $this
     */
    public function setCookie($name, $value = null, $expire = 0, $path = null, $domain = null, $secure = false, $httpOnly = false)
    {
        $this->cookies[$name] = func_get_args();
        return $this;
    }


    /**
     * Retourne le cookie dont le nom est pass� en param�tre ou <code>false</code> si le cookie
     * indiqu� n'existe pas.
     *
     * @param string $name le nom du cookie � retourner.
     *
     * @return array|false un tableau contenant les param�tres du cookie ou <code>false</code> si
     * le cookie demand� n'existe pas
     *
     * Le tableau retourn� peut avoir les cl�s suivantes :
     * - <code>name</code> : le nom du cookie.
     * - <code>value</code> : la valeur du cookie.
     * - <code>expire</code> : la dur�e de vie du cookie.
     * - <code>path</code> : le chemin du cookie.
     * - <code>domain</code> : le domaine du cookie.
     * - <code>secure</code> : flag indiquant un cookie https.
     * - <code>httpOnly</code> : flag indiquant un cookie accessible uniquement par HTTP.
     *
     * Important :
     * Seuls les arguments effectivements pass�s en param�tre lors de l'appel � {@link setCookie()}
     * figurent dans le tableau retourn�.
     */
    public function getCookie($name)
    {
        static $keys = array('name', 'value', 'expire', 'path', 'domain', 'secure', 'httpOnly');

        if (! isset($this->cookies[$name]))
            return false;

        return array_combine
        (
            array_slice($keys, 0, count($this->cookies[$name])),
            $this->cookies[$name]
        );
    }


    /**
     * Retourne tous les cookies actuellement d�finits dans la requ�te.
     *
     * @return array retourne un tableau dont les cl�s correspondent au nom des cookies et dont
     * la valeur est un tableau tel que retourn� par {@link getCookie()}.
     *
     * Remarque :
     * Si aucun cookie n'est d�finit dans la requ�te, la m�thdoe retourne un tableau
     * vide.
     */
    public function getCookies()
    {
        $result = array();
        foreach($this->cookies as $name=>$cookie)
            $result[$name] = $this->getCookie($name);
        return $result;
    }


    /**
     * Efface tous les cookies actuellement d�finits pour la requ�te.
     *
     * @return Response $this
     */
    public function clearCookies()
    {
        $this->cookies = array();
        return $this;
    }



    /**
     * G�n�re les ent�tes http de la r�ponse.
     *
     * Si les ent�tes ont d�j� �t� envoy�s ({@link http://php.net/headers_sent() headers_sent()}
     * retourne true), la m�thode ne fait rien et aucun warning n'est �mis.
     *
     * @return Response $this
     */
    protected function outputHeaders()
    {
        // Si les ent�tes ont d�j� �t� envoy�s, on ne peut pas envoyer les notres
        if (headers_sent()) return $this;

        // Statut http
        if ($this->status)
        {
            if (is_null($this->version)) $this->setVersion();
            header(sprintf('HTTP/%s %s %s', $this->version, $this->status, self::$httpStatus[$this->status]));
        }

        // Ent�tes http
        foreach ($this->headers as $name => $value)
            foreach((array)$value as $item)
                header("$name: $item");

        // Cookies
        foreach ($this->cookies as $cookie)
            call_user_func_array('setrawcookie', $cookie);

        return $this;
    }


    /**
     * G�n�re le contenu de la r�ponse.
     *
     * @return Response $this
     */
    public function outputContent()
    {
        // Contenu statique
        echo $this->content;

        // Template
        if ($this->template)
            Template::runInternal($this->template, $this->templateData);

        // Done
        return $this;
    }


    /**
     * G�n�re la r�poonse compl�te ({@link outputHeaders() ent�tes} et
     * {@link outputContent() contenu}).
     *
     * @return Response $this
     */
    public function output()
    {
        $this->outputHeaders();
        $this->outputContent();
        return $this;
    }


    /**
     * G�n�re le contenu de la r�ponse et le retourne sous forme de chaine de caract�res.
     *
     * Remarque : <code>render()</code> ne g�n�re que la r�ponse. Les ent�tes http, les cookies et
     * le layout �ventuel ne sont ni retourn�s, ni envoy�s au navigateur.
     *
     * @return string
     */
    public function render()
    {
        ob_start();
        $this->outputContent();
        return ob_get_clean();
    }


    /**
     * Indique si la r�ponse peut avoir un layout.
     *
     * @return bool retourne <code>true</code> si la r�ponse en cours est une instance de
     * {@link LayoutResponse} ou de ses descendants, <code>false</code> sinon.
     */
    public function hasLayout()
    {
        return $this instanceof LayoutResponse;
    }


    /**
     * M�thode utilitaire permettant de g�n�rer une exception de type BadMethodCallException si
     * l'appel d'une m�thode n'est pas autoris�.
     *
     * @param string|null $message texte additionnel � ajouter au mesage de l'exception.
     */
    protected function illegal($message=null)
    {
        if ($message) $message = ' : ' . $message;
        throw new BadMethodCallException('Op�ration ill�gale pour une r�ponse de type '. get_class($this) . $message);
    }
}