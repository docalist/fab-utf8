<?php
/**
 * @package     fab
 * @subpackage  routing
 * @author 		Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: Routing.php 1202 2010-09-10 10:10:37Z daniel.menard.bdsp $
 */


/**
 * Gestionnaire de routes.
 *
 * Les gestionnaire de routes permet d'avoir des urls s�mantiques. Il travaille
 * � partir d'un fichier de configuration (routing.config) qui pour chacune des
 * urls que l'utilisateur peut appeller d�finit le module et l'action �
 * appeller ainsi que les param�tres � passer.
 *
 * Le fichier est aussi utilis� en sens inverse : lorsque dans un template on
 * fait un lien vers une autre action ou un autre module, le lien est
 * automatiquement ajust� en fonction de la configuration.
 *
 * @package     fab
 * @subpackage  routing
 */
class Routing
{
    /**
     * Compile un tableau contenant des routes telles qu'elle figurent dans
     * le fichier de configuration XML en tableau index� permettant �
     * {@link linkFor()} et {@link routeFor()} de travailler de fa�on efficace.
     *
     * La compilation consiste � cr�er deux index (un pour chacune des deux
     * fonctions).
     *
     * todo: voir (faire) doc pour plus de d�tails sur le format des tableaux
     *
     * @param array $config tableau contenant les routes
     * @return array tableau contenant la version compil�e des routes
     */
    public static function transform(array $routes)
    {
        // Les deux index qu'on va g�n�rer
        $urls=array();
        $modules=array();

        // Compile chaque route une par une, dans l'ordre indiqu�
        foreach($routes as $route)
        {
            // R�cup�re et v�rifie l'url indiqu�e dans la route
            if (!isset($route['url']) || '' === $url=trim($route['url']))
            {
                debug && Debug::warning('Route invalide, url non indiqu�e : %s', $route);
                continue;
            }

            if($url[0] !== '/')
            {
                debug && Debug::warning('Route invalide, l\'url ne commence pas par un slash : %s', $route);
                continue;
            }

            // D�coupe l'url en parties et compl�te le tableau $urls
            $parts = & $urls;
            foreach(self::urlParts($url) as $part)
            {
                // Si urlignorecase=true, on stocke la version minu des bouts d'url, sinon, on stocke tel quel
                if (Config::get('urlignorecase')) $part=strtolower($part);

                // Variable � cet emplacement : ne stocke pas le nom mais juste '$'
                if ($part[0]==='$') $part='$';

                // Ajoute cette partie dans le tableau si elle n'existe pas d�j�
                if (!isset($parts[$part])) $parts[$part]=array();

                // Continue avec le bout en cours
                $parts=& $parts[$part];
            }


            // Cr�e la liste des arguments ($xx) qui figurent dans l'url
            preg_match_all('~\$([A-Za-z0-9_]+)~', $url, $args, PREG_PATTERN_ORDER|PREG_OFFSET_CAPTURE);
            $args=$args[1]; // un tableau index=>(arg,position)

            // V�rifie et finalise les expressions r�guli�res indiqu�es dans la route (with)
            if (isset($route['with']))
            {
                foreach($route['with'] as $name => & $regexp)
                {
                    foreach($args as $arg)
                    {
                        if ($arg[0]===$name)
                        {
                            $regexp='~^'.$regexp.'$~';
                            continue 2;
                        }
                    }
                    debug && Debug::warning('Route incorrecte, la variable %s indiqu�e dans l\'attribut with ne figure pas dans l\'url : %s', $name, $route);
                }
            }

            /*
               Cr�e deux versions de la route :
               - $urlRoute qui sera stock�e dans $urls et qui sera utilis�e par
                 routeFor()
               - $modRoute qui sera stock�e dans $modules et qui sera utilis�e
                 par linkFor()

               Les deux versions ne diff�rent que par la mani�re dont les
               arguments sont stock�s (propri�t� 'args' de la route).

               Pour $urlRoute : les arguments sont stock�s dans l'ordre exact o�
               ils apparaissent dans l'url. Le nom de l'argument est la cl� et
               la valeur associ�e est le num�ro d'ordre de la variable dans l'url.
               Si une variable appara�t plusieurs fois dans l'url, la valeur est
               alors un tableau contenant (en ordre croissant) les diff�rents
               occurences.
               cf. routeForRecurse().

               Pour $modRoute : les arguments sont stock�s dans l'ordre inverse
               de l'ordre dans lequel ils apparaissent dans l'url (parce que
               linkFor fait le remplacement de la fin vers le d�but).
               Le nom de l'argument est la cl� est la valeur associ�e est la
               position exacte, au sein de l'url, � laquelle l'argument apparait.
               Si une variable appara�t plusieurs fois dans l'url, la valeur est
               alors un tableau contenant (en ordre d�croissant, pour la m�me
               raison) les diff�rents positions.
               Cette structure permet � linkFor() de remplacer la variable par
               sa valeur directement au bon endroit, sans avoir � rechercher le
               nom de la variable.
            */
            $modRoute=$urlRoute=$route;
            if (count($args))
            {
                $modRoute['args']=$urlRoute['args']=array();
                foreach($args as $index=>$arg)
                {
                    $name=$arg[0];
                    $position=$arg[1]-1;// -1 = pour pointer sur le '$' et non pas sur le d�but du nom
                    Utils::arrayAppendKey ($urlRoute['args'], $name, $index);    // arg=>index, ordre normal
                    Utils::arrayPrependKey($modRoute['args'], $name, $position); // arg=>position, ordre inverse
                }
            }
            $parts['@route']=$urlRoute;

            // Ajoute cette route dans le tableau $modules
            if (isset($modRoute['module']))
                $module=strtolower($modRoute['module']); // minusculise toujours le nom du module pour que linkFor('/xxx/yyy') soit insensible � la casse. Ind�pendant de l'option urlignorecase
            else
                $module='$';

            if (isset($modRoute['action']))
            {
                $action=strtolower($modRoute['action']); // minusculise toujours le nom de l'action, m�me raison que ci-dessus
                if (strncmp($action, 'action', 6)===0)
                    $action=substr($action, 6);
            }
            else
                $action='$';

            $modules[$module.'-'.$action][]=$modRoute;
        }

        // Construit le tableau r�sultat qui sera stock� dans la config
        $routes=array
        (
            'urls'=>$urls,
            'modules'=>$modules
        );

        // Retourne le tableau final de routes qui sera mis en cache
        return $routes;
    }

    /**
     * Dispatche une url
     *
     * dispatch() analyse l'url pass�e en param�tre et examine les routes
     * pr�sentes dans la configuration pour d�terminer le nom du module et de
     * l'action correspondant � cette url.
     *
     * Une exception est g�n�r�e si aucune route n'accepte l'url examin�e.
     *
     * Dans le cas contraie, un objet {@link Request} contenant les param�tres
     * de la requ�te est cr��, le module obtenu est charg� et l'ex�cution de
     * l'action est lanc�e.
     *
     * @param string $url l'url demand�e par l'utilisateur
     *
     * @throws Exception si aucune des routes pr�sentes dans la configuration
     * en cours n'accepte l'url indiqu�e.
     */
    public static function dispatch($url)
    {
        // D�termine une route pour cette url
        if (! $route=self::routeFor($url))
            throw new RouteNotFoundException('Route not found');

        // Cr�e et initialise un nouvel objet Request
        $request = Request::create($_GET, $_POST, $route['args'])
            ->setModule($route['module'])
            ->setAction($route['action']);

        // Hack : si c'est le premier dispatch qu'on ex�cute,
        // Stocke la requ�te obtenue dans le runtime pour
        // que Template::runSlot() puis y acc�der
        if (is_null(Runtime::$request))
            Runtime::$request=$request;

        Module::run($request);
    }


    /**
     * D�termine une route pour une url donn�e
     * (l'url ne doit pas contenir de query string)
     *
     * @param string $url
     * @return array|false
     */
    public static function routeFor($url)
    {
        return self::routeForRecurse
        (
            Config::get('routing.urls'), // La partie "index par morceaux" des routes
            self::urlParts($url)        // L'url d�coup�e en morceaux
        );
    }

    /**
     * Fonction r�cursive utilis�e par {@link routeFor()} pour d�terminer une
     * route � partir d'une url
     *
     * @param array $routes
     * @param unknown_type $parts
     * @param unknown_type $index
     * @param unknown_type $vars
     * @return unknown
     */
    private static function routeForRecurse(array $routes, array $parts, $index=0, $vars=array())
    {
        $trace=false;

        // On a �tudi� toutes les parties de l'url, teste les routes restantes
        if ($index>=count($parts))
        {
            // V�rifie qu'il existe des routes pour ce qu'on a �tudi�
            if(!isset($routes['@route']))
            {
                if($trace)echo 'pas de @route<br />';
                return false;

            }

            // R�cup�re la route obtenue
            $route=$routes['@route'];

            if($trace)
            {
                echo 'route � instancier : ', var_export($route), '<br />';
                echo 'Vars : ', var_export($vars, true), '<br />';
            }

            // V�rifie que toutes les variables respectent le masque indiqu� par 'with'
            if (isset($route['with']))
            {
                foreach($route['with'] as $name=>$regexp) // n�cessaire si l'argument indiqu� par le with est r�p�t� dans la route
                {
                    foreach((array) $route['args'][$name] as $i)
                    {
                        if (!preg_match($regexp, $vars[$i]))
                        {
                            if($trace)echo 'argument ', $name, '=', $vars[$i], ' colle pas avec son with<br />';
                            return false;
                        }
                        else
                            if($trace)echo 'argument ', $name, '=', $vars[$i], ' colle avec son with=', $regexp, '<br />';
                    }
                }
            }

            // Stocke les arguments
            $args=array();
            if (isset($route['args']))
            {
                foreach($route['args'] as $name=>$index)
                {
                    foreach((array)$index as $index)
                    {
                        if($trace)echo 'storing args, name=', $name, ', value=', $vars[$index], '<br />';

                        if (!isset($route[$name]) && ($name==='module' || $name==='action'))
                        {
                            if ($name==='action')
                                $route[$name]=$vars[$index];
                            else
                                $route[$name]=$vars[$index];
                        }
                        else
                            Utils::arrayAppendKey($args, $name, $vars[$index]);
                    }
                }
            }

            if (isset($route['add']))
            {
                foreach($route['add'] as $name=>$value)
                {
                    Utils::arrayAppendKey($args, $name, $value);
                }
            }

            $route['args']=$args;

            if($trace) echo '<pre>', var_export($route,true), '</pre>';

            // Trouv� !
            return $route;
        }

        // Ignore la casse dans les urls si l'option urlignorecase=true
        $part=$parts[$index];
        if (Config::get('urlignorecase')) $part=strtolower($part);

//        var_export($routes);
        // Recherche parmi les routes qui ont cette partie � cet endroit
        if (isset($routes[$part]))
        {
            if($trace)echo 'texte. part=', $part, '<blockquote style="border: 1px solid #888;margin:0 2em">';
            $route=self::routeForRecurse($routes[$part], $parts, $index+1, $vars);
            if($trace)echo '</blockquote>';
            if ($route !== false) return $route;
        }
        else if($trace) echo 'routes[',$part,'] not set<br />';

        // Sinon, rechercher parmi les routes qui autorisent une variable � cet endroit
        if (isset($routes['$']))
        {
            //$vars[]=$part;
            $varIndex=count($vars);
            $vars[$varIndex]='';
            if($trace)echo 'index=', $index, ', var=', $vars[$varIndex], '<br />';
            for($i=$index+1; $i<=count($parts); $i++)
            {
                $vars[$varIndex].=urldecode($parts[$i-1]);
                if($trace)echo 'var. part=', $part, ', var=', $vars[$varIndex], '<blockquote style="border: 1px solid #888;margin:0 2em">';
                $route=self::routeForRecurse($routes['$'], $parts, $i, $vars);
                if($trace)echo '</blockquote>';
                if ($route !== false) return $route;
            }
        }
        else if($trace)echo '$ not set';

        // Aucune route ne convient dans cette branche
        if($trace)echo 'ni "', $part, '" ni variable ne sont autoris�s ici<br />';
        if($trace)var_export($routes);

        return false;
    }






    /**
     * G�n�re un lien � partir d'une fab url.
     *
     * Une fab url est en g�n�ral de la forme "/module/action?querystring",
     * mais il est possible d'omettre le module, l'action ou les deux :
     *
     * - /module/action?query : action "action" du module "module"
     * - /module/?query : action par d�faut (index) du module module
     * - /module?query : todo: autoris� ou non ? redirection ou non ?
     * - /?query : action par d�faut du module par d�faut (la page d'accueil du site)
     * - action?query : action "action" du module en cours
     * - ?query : action en cours du module en cours
     *
     * La fonction utilise le module, l'action et les param�tres indiqu�s en
     * query string pour d�terminer, parmi les routes pr�sentes dans la
     * configuration en cours, celle qui s'applique.
     *
     * Si aucune route n'est trouv�e, un warning est �mis et l'url est retourn�e
     * telle quelle. Sinon, la fonction instancie la route trouv�e et retourne
     * l'url obtenue.
     *
     * Par d�faut, la fonction cr�e des liens relatifs (qui ne mentionnent ni
     * le protocole ni le nom de domaine). Vous pouvez indiquer absolute=true
     * pour g�n�rer des urls absolues (utile par exemple pour g�n�rer un e-mail
     * au format html).
     *
     * todo: les liens retourn�s sont de la forme /index.php/xxx/yyy, c'est-�-dire
     * qu'ils partent toujours de la racine du site. Ce serait bien de pouvoir
     * g�n�rer des liens relatifs au module et � l'action en cours (par exemple,
     * si je suis dans '/base/search?xxx' et que j'appelle 'show?yyy', g�n�rer
     * simplement 'show?yyy' et non pas '/base/show?yyy' ; si je suis d�j�
     * dans '/base/show?yyy' et que j'appelle 'show?zzz', g�n�rer simplement
     * '?zzz'.
     *
     * @param string $url la fab url pour laquelle on souhaite cr�er un lien
     *
     * @param boolean $absolute indique s'il faut g�n�rer un lien relatif ou non
     * (par d�faut : false)
     * todo: est-que cela ne devrait pas plut�t �tre dans la config ?
     *
     * @return string
     */
    public static function linkFor($url, $absolute=false)
    {
        $url=(string)$url; // au cas o� on nous passe autre chose qu'une chaine (par exemple un objet Request)

        // Si ce n'est pas une fab url, on retourne l'url telle quelle
        if (preg_match('~^(?:[a-z]{3,10}:|\?|#)~',$url))  // commence par un nom de protocole, une query string ou un hash (#)
            return $url;

        $ori=$url;

        // Supprime l'ancre �ventuelle
        $pt=strpos($url, '#');
        if ($pt === false)
            $anchor='';
        else
        {
            $anchor=substr($url, $pt);
            $url=substr($url, 0, $pt);
        }

        // Analyse la query string, initialise $args()
        $args=array();
        $pt=strpos($url, '?');
        if ($pt === false)
            $query='';
        else
        {
            $query=substr($url, $pt+1);
            if (strlen(ltrim($query)))
            {
                foreach (explode('&', $query) as $arg)
                {
                    list($name,$value)=array_pad(explode('=', $arg), 2, null);
                    Utils::arrayAppendKey($args, $name, $value);
                }
            }
            $url=substr($url, 0, $pt);
        }

        // D�termine le module et l'action demand�s

        // Pas d'url -> on prends le module et l'action en cours
        if ($url==='')
        {
            $module=Runtime::$request->getModule(); // module en cours
            $action=Runtime::$request->getAction(); // action en cours
            if (strncmp($action, 'action', 6)===0)
                $action=substr($action, 6);
        }

        // Raccourci pour la page d'accueil du site
        elseif($url==='/')
        {
            // Ajoute la webroot
            $link= Runtime::$home;
            if ($query) $link .= '?' . $query;

            // Cr�e une url absolue si �a a �t� demand�
            if ($absolute)
                $link=Utils::getHost() . $link . $anchor;
            return $link;
        }

        // Commence par un '/' -> lien vers un module
        elseif ($url[0] === '/')
        {
            $url=ltrim($url, '/'); // supprime le slash initial
            $pt=strpos($url, '/');
            if ($pt===false)
            {
                $module=$url;
                $action='index'; // action par d�faut
            }
            else
            {
                $module=substr($url, 0, $pt);
                $action=substr($url, $pt+1);
                if ($action=='') $action='index';
            }

            // Si le "module" est un sous-r�pertoire existant du r�pertoire web
            // de l'application on retourne un lien r�el vers celui-ci.
            // Peu-importe que le fichier existe r�ellement ou non (il peut y
            // avoir une redirection dans le .htaccess, etc...), on ne teste que
            // l'existence du r�pertoire de plus haut niveau (ie $module).
            if (file_exists(Runtime::$webRoot . $module))
            {
                if ($query) $url .= '?' . $query;
                return ($absolute ? Utils::getHost() : '') . Runtime::$realHome . $url . $anchor;
            }
        }

        // pas de slash au d�but -> lien vers une action du module en cours
        else
        {
            $module=Runtime::$request->getModule(); // module en cours
            $action=$url; // rtrim($url,'/');
        }

        // supprime le slash final de l'action
        if (strpos($action, '/')===strlen($action)-1) // enl�ve le '/' de fin dans 'index/' mais pas dans '/styles/default/'
        {
            $action=rtrim($action,'/');
        }

        if (false === $link=self::linkForRoute($module, $action, $args))
        {
            debug && Debug::warning('linkFor : aucune route applicable pour l\'url %s', $url);
            $link=$ori;
        }

        // Ajoute la webroot
        if (User::hasAccess('cli') && Config::get('smarturls',false))
            $link= rtrim(Runtime::$realHome,'/') . $link;
        else
            $link= rtrim(Runtime::$home,'/') . $link;

        // Cr�e une url absolue si �a a �t� demand�
        if ($absolute)
            $link=Utils::getHost() . $link;

        // Retourne le r�sultat
//        debug && Debug::log('linkFor(%s)=%s', $url, $link);
        return $link . $anchor;
    }

    private static function linkForRoute($module, $action, array $args=array())
    {
        if (!defined('trace')) define('trace',false);

        $ori=$args;
        if(trace) echo '<h1>linkForRoute</h1>module=', $module, ', action=', $action, ', args=', var_export($args,true), '<br /><pre>';

        // linkForRoute('/xxx/yyy') n'est pas sensible � la casse. Transform stocke toujours la version minu, donc il faut rechercher de la m�me fa�on
        $lowerModule=strtolower($module);
        $lowerAction=strtolower($action);

        // R�cup�re la liste des routes possibles pour ce couple (module,action)
        $routes=Config::get("routing.modules.$lowerModule-$lowerAction");
        if (is_null($routes))
        {
            if(trace)echo 'Pas de routes pour ', "routing.modules.$lowerModule-$lowerAction", '<br />';
            $routes=Config::get("routing.modules.\$-$lowerAction");
            if (is_null($routes))
            {
                if(trace)echo 'Pas de routes pour ', "routing.modules.\$-$lowerAction", '<br />';
                $routes=Config::get('routing.modules.$-$');
                if (is_null($routes))
                {
                    if(trace)echo 'Pas de routes pour ', 'routing.modules.$-$', '<br />';
                    if(trace)echo '<pre>', var_export(Config::get('routes'), true), '</pre>';
                    if ($action==='index') $action='';
                    $url='/'.$module.'/'.$action.self::buildQueryString($args);
                    if (trace)echo 'Aucune route pour module=', $module, ' & action=', $action, ', reconstruction de l\'url, result=', $url, '<br /></pre>';
                    return $url;
                }

            }
        }


        // Ne garde que celles qui conviennent
        foreach($routes as $route)
        {
            $args=$ori;

            if(trace)echo 'Route �tudi�e : ', var_export($route,true), '<br />';

            // Si la route a des arguments, il faut les v�rifier
            if (isset($route['args']))
            {
                // Cas particulier : module et route sont d�finis par l'url, il faut les consid�rer comme des param�tres
                if (isset($route['args']['module']))
                    Utils::arrayPrependKey($args, 'module', $module);
                if (isset($route['args']['action']))
                    Utils::arrayPrependKey($args, 'action', $action);

                // On doit avoir au moins autant d'arguments que dans la route
                if (count($args) < count($route['args']))
                {
                    if(trace)echo 'nombre de args insuffisant<br />';
                    continue;
                }

                // Chacun des arguments de la route doit �tre pr�sent
                foreach($route['args'] as $name=>$value)
                {
                    if (!isset($args[$name]))
                    {
                        if(trace)echo'arg ', $name, ' non fourni<br />';
                        continue 2;
                    }
                    if(is_array($value) && count($args[$name])<count($value))
                    {
                        if(trace) echo 'nb de valeurs fournies pour arg ', $name, ' insuffisant<br />';
                        continue 2;
                    }
                }

                // Les arguments doivent avoir le bon type (regexp)
                if (isset($route['with']))
                {
                    foreach($route['with'] as $name=>$regexp)
                    {
                        foreach((array)$args[$name] as $value)
                            if (!preg_match($regexp, $value))
                            {
                                if(trace)echo 'valeur ', $value, 'de arg ', $name, ' ne correspond pas au masque ', $regexp, '<br />';
                                continue 3;
                            }
                    }
                }
            }

            // Si on a des 'add' dans l'url ils doivent figurer dans les arguments
            if (isset($route['add']))
            {
                foreach ($route['add'] as $name=>$value)
                {
                    if (!isset($args[$name]))
                    {
                        if(trace)echo 'ADD ', $name, ' non fourni<br />';
                        continue 2; // le add de la route n'a pas �t� indiqu�
                    }

                    if (is_array($value))
                    {
                        if (count($value) > count($args[$name]))
                        {
                            if(trace) echo 'nombre de valeurs pour ADD ', $name, ' insuffisant<br />';
                            continue 2; // n valeurs sp�cifi�es dans le add, moins que �a dans les args
                        }

                        foreach($value as $value)
                        {
                            if (false === $i=array_search($value, $args[$name], false)) // non-strict
                            {
                                if(trace) echo 'valeur ', $value, ' du ADD ', $name, ' non fournie<br />';
                                continue 3; // la valeur indiqu�e dans le add n'est pas dans les args
                            }
                            unset($args[$name][$i]);
                        }
                        if (count($args[$name])===0)
                        unset($args[$name]);
                    }
                    else
                    {
                        if ($value!=$args[$name]) // non-strict
                        {
                            if(trace) echo 'valeur ', $value, ' du ADD ', $name, ' non fournie<br />';
                            continue 2; // la valeur indiqu�e dans le add est diff�rente
                        }
                        unset($args[$name]);
                    }
                }
            }

            // Tout est ok, cr�e le lien en instanciant l'url
            $url=$route['url'];
            if (isset($route['args']))
            {
                foreach($route['args'] as $name=>$position)
                {
                    foreach((array)$position as $position)
                    {
                        if (is_array($args[$name]))
                        {
                            $arg=array_pop($args[$name]);
                        }
                        else
                        {
                            $arg=$args[$name];
                            unset($args[$name]);
                        }
                        $url=substr_replace($url, $arg, $position, strlen($name)+1); // +1 pour le signe $
                    }
                }
            }

            // S'il reste des arguments, on les ajoute en query-string
            if(trace)echo 'url instanci�e : ', $url, ', args restants : ', var_export($args,true), '<br />';
            $url.=self::buildQueryString($args);

            return $url;
        }

        return false;
    }


    public static function notFound()
    {
        static $nb=0;
        if (++$nb>3) die('loop dans notfound');
        self::dispatch('/NotFound/'); // TODO: mettre dans la config le nom du module 'not found'
        Runtime::shutdown();
    }


    /**
     * D�coupe une url en morceaux
     *
     * @param string $url
     * @return array
     */
    public static function urlParts($url)
    {
        // Ignore le slash de d�but, �vite un niveau d'indirection inutile
        $url=ltrim($url,'/');
        $parts=array();
        for($start=0; $start<strlen($url);)
        {
            if ($url[$start]==='$')
            {
                $len=1+strspn($url, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_', $start+1);
            }
            else
            {
                $len=strcspn($url, '-,;/$.+', $start);
            }

            $parts[]=substr($url, $start, $len);
            $start+=$len;
            if ($start < strlen($url) && $url[$start]!=='$')
            {
                $parts[]=$url[$start];
                ++$start;
            }
        }
        return $parts;
    }

    /**
     * Construit une query string � partir de la liste d'arguments pass�s en
     * param�tre.
     *
     * Contrairement � la fonction php standard
     * {@link http://php.net/http_build_query http_build_query()}, cette
     * m�thode g�re correctement les arguments multi-valu�s (ie elle ne g�n�re
     * pas de crochets).
     *
     * Par d�faut, la fonction consid�re que les arguments sont d�j� encod�s
     * correctement. Dans le cas contraire, vous pouvez passer 'encode=true'
     * en param�tre. Dans ce cas, le nom et la valeur des arguments sera encod�e
     * en utilisant la fonction php {@link http://php.net/rawurlencode rawurlencode()}
     * conform�ment � la {@link http://www.faqs.org/rfcs/rfc1738 RFC 1738}.
     *
     * Exemple :
     * <code>
     * $args=array('ref'=>12, 'mcl'=>array('�tre', 'ne pas �tre'));
     * resultat : ?ref=12&mcl=%EAtre&mcl=ne%20pas%20%EAtre
     * </code>
     *
     * @param array $args les arguments � mettre en query string
     *
     * @param bool $encode indique s'il faut encoder ou non les donn�es avec
     * la fonction php rawurlencode() (default : true)
     *
     * @param null|string $separator le s�parateur � utiliser entre les
     * param�tres. Par d�faut ($separator=null), la valeur de l'option de
     * configuration 'arg_separator.output' de php.ini est utilis�e (en g�n�ral
     * il s'agit de la chaine '&').
     *
     * @return string une chaine vide si $args �tait vide, une query string
     * valide commen�ant par '?' sinon.
     *
     * @see http://www.faqs.org/rfcs/rfc1738
     */
    public static function buildQueryString(array $args, $encode=false, $separator=null)
    {
        if (is_null($separator)) $separator=ini_get('arg_separator.output');
        //$separator='&amp;';  -> c'est ce qu'il faudrait avoir si on g�n�re du xml. Pb : on ne sait pas ce qu'on g�n�re...
        $query='';
        if ($encode)
        {
            foreach($args as $name=>$value)
            {
                if (is_null($value))
                    $query.=(strlen($query) ? $separator : '?').rawurlencode($name);
                else
                {
                    foreach((array)$value as $value)
                        $query.=(strlen($query) ? $separator : '?').rawurlencode($name).'='.rawurlencode($value);
                }
            }
        }
        else
        {
            foreach($args as $name=>$value)
            {
                if (is_null($value))
                    $query.=(strlen($query) ? $separator : '?').$name;
                else
                {
                    foreach((array)$value as $value)
                        $query.=(strlen($query) ? $separator : '?').$name.'='.$value;
                }
            }
        }
        return $query;
    }
}
class RouteNotFoundException extends Exception {}
?>