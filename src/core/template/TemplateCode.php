<?php
/**
 * @package     fab
 * @subpackage  template
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: TemplateCode.php 1247 2011-03-15 16:15:02Z daniel.menard.bdsp $
 */

/**
 * Analyseur de code du gestionnaire de templates
 *
 * @package     fab
 * @subpackage  template
 */
class TemplateCode
{
    const PHP_START_TAG='<?php ';
    const PHP_END_TAG="?>";

    /**
     * Pour l'analyse des expressions, on utilise token_get_all, mais on ajoute
     * deux tokens qui n'existent pas en standard : T_CHAR pour les caract�res et
     * T_END qui marque la fin de l'expression. Cela nous simplifie l'analyse.
     */
    const T_CHAR=20000; // les tokens actuels de php vont de 258 � 375, peu de risque de conflit...
    const T_END=20001;


    /**
     * Analyse une chaine contenant une expression php et retourne un tableau contenant
     * les tokens correspondants
     *
     * @param string $expression l'expression � analyser
     * @return array les tokens obtenus
     */
    public static function tokenize($expression)
    {
        // Utilise l'analyseur syntaxique de php pour d�composer l'expression en tokens
        ob_start();
        $tokens = token_get_all(self::PHP_START_TAG . $expression . self::PHP_END_TAG);
        $warning=ob_get_clean();
        if ($warning !== '')
            throw new Exception("Une erreur s'est produite durant l'analyse de l'expression :<br />".$expression.'<br />'.$warning);

        // Enl�ve le premier et le dernier token (PHP_START_TAG et PHP_END_TAG)
        array_shift($tokens);
        array_pop($tokens);

        // Supprime les espaces du source et cr�e des tokens T_CHAR pour les caract�res
        foreach ($tokens as $index=>$token)
        {
            // Transforme en T_CHAR ce que token_get_all nous retourne sous forme de chaines
            if (is_string($token))
                $tokens[$index]=array(self::T_CHAR, $token);

            // Supprime les espaces
            elseif ($token[0]==T_WHITESPACE)
            {
                // Si les blancs sont entre deux (chiffres+lettres), il faut garder au moins un blanc
                if (    isset($tokens[$index-1]) && isset($tokens[$index+1])
                     && (ctype_alnum(substr($tokens[$index-1][1],-1)))
                     && (ctype_alnum(substr(is_string($tokens[$index+1]) ? $tokens[$index+1] : $tokens[$index+1][1],0,1)))
                   )
                    $tokens[$index][1]=' ';

                // Sinon on peut supprimer compl�tement l'espace
                else
                    unset($tokens[$index]);
            }

            // Supprimer les commentaires
            elseif ($token[0]==T_COMMENT || $token[0]==T_DOC_COMMENT)
                unset($tokens[$index]);
        }

        // Comme on a peut-�tre supprim� des tokens, force une renum�rotation des index
        $tokens=array_values($tokens);

        // Ajoute la marque de fin (T_END)
        $tokens[]=array(self::T_END,null);

        // Retourne le tableau de tokens obtenu
        return $tokens;
    }


    /**
     * G�n�re l'expression PHP correspondant au tableau de tokens pass�s en param�tre
     *
     * Remarque : les tokens doivent avoir �t� g�n�r�s par {@link tokenize()}, cela
     * ne fonctionnera pas avec le r�sultat standard de token_get_all().
     *
     * @param string $tokens le tableau de tokens
     * @return string l'expression php correspondante
     */
    public static function unTokenize($tokens)
    {
        $result='';
        foreach ($tokens as $token)
            $result.=$token[1];
        return $result;
    }


    /**
     * Affiche les tokens pass�s en param�tre (debug)
     *
     * @param array $tokens un tableau de tokens tel que retourn� par {@link tokenize()}
     * @return void
     */
    private static function dumpTokens($tokens)
    {
        echo '<pre>';
        foreach($tokens as $index=>$token)
        {
            echo gettype($token), ' => ', $index, '. ';
            switch($token[0])
            {
                case self::T_CHAR:
                    echo 'T_CHAR';
                    break;

                case self::T_END:
                    echo 'T_END';
                    break;

                default:
                    echo token_name($token[0]);
            }
            echo ' : [', $token[1], ']', "<br />";
        }
//        var_export($tokens);
        echo '</pre>';
    }

    private static $currentExpression=null;

    /**
     * Evalue l'expression PHP pass�e en param�tre et retourne sa valeur.
     *
     * @param string $expression l'expression PHP � �valuer
     * @return mixed la valeur obtenue
     * @throws Exception en cas d'erreur.
     */
    public static function evalExpression($expression)
    {
        if (trim($expression)==='') return null;

        // Supprime les �ventuelles accolades autour de l'expression
        if ($expression[0]==='{') $expression=substr($expression, 1, -1);

        // Capture la sortie �ventuellement g�n�r�e lors de l'�valuation
        ob_start();

        // Installe un gestionnaire d'exception sp�cifique
        self::$currentExpression=$expression;
        set_error_handler(array(__CLASS__,'evalError'));

        // Ex�cute l'expression
        $result=eval('return '.$expression.';');

        // Restaure le gestionnaire d'exceptions pr�c�dent
        restore_error_handler();
        self::$currentExpression=null;

        // R�cup�re la sortie �ventuelle
        $h=ob_get_clean();

        // L'�valuation n'a pas g�n�r� d'exception, mais si une sortie a �t� g�n�r�e (un warning, par exemple), c'est une erreur
        if ($h !=='')
            throw new Exception('Erreur dans l\'expression PHP [ ' . $expression . ' ] : ' . $h);
//        echo 'eval(', $expression, ') = '; var_dump($result); echo '<br />';
        // Retourne le r�sultat
        return $result;
    }

    /**
     * Gestionnaire d'erreurs appell� par {@link evalExpression} en cas d'erreur
     * dans l'expression �valu�e
     */
    private static function evalError($errno, $errstr, $errfile, $errline, $errcontext)
    {
        // errContext contient les variables qui existaient au moment de l'erreur
        // Celle qui nous int�resse, c'est l'expression pass�e � eval
        // on supprimer le 'return ' de d�but et le ';' de fin (cf evalExpression)
        //$h=substr(substr($errcontext['expression'], 7), 0, -1);

        // G�n�re une exception
        throw new Exception('Erreur dans l\'expression PHP [ ' . self::$currentExpression . ' ] : ' . $errstr . ' ligne '.$errline);
    }

    /**
     * Analyse et optimise une expression PHP
     *
     * - appelle une fonction utilisateur lorsqu'une variable est rencontr�e
     * - permet d'avoir dans le code de pseudo-fonctions qui vont �tre appell�es si
     *   elles sont rencontr�es
     * - les affectations de variables sont interdites (=, .=, &=, |=...)
     * - g�re une liste de fonctions autoris�es, g�n�re une exception si une fonction
     *   non autoris�e est appell�e
     * - optimise les expressions en �valuant tout ce qu'il est possible d'�valuer
     *
     * TODO: compl�ter la doc
     *
     * @param mixed $expression l'expression � analyser. Cette expression peut �tre
     * fournie sous la forme d'une chaine de caract�re ou sous la forme d'un tableau
     * de tokens tel que retourn� par {@link tokenize()}.
     *
     * @param mixed $varCallback la m�thode � appeller lorsqu'une variable est rencontr�e
     * dans l'expression analys�e. Si vous indiquez 'null', aucun traitement ne sera effectu�
     * sur les variables. Sinon, $varCallback doit �tre le nom d'une m�thode de la classe Template
     * TODO: � revoir
     *
     * @param array|null $pseudoFunctions un tableau de pseudo fonctions ("nom pour l'utilisateur"=>callback)
     */
    public static function parseExpression(&$expression, $varCallback=null, $pseudoFunctions=null)
    {
        // Indique si l'expression en cours est �valuable
        // True par d�faut, passe � faux quand on rencontre une variable, l'op�rateur '=>' dans un array() ou
        // quand on tombe sur quelque chose qu'on ne sait pas �valuer (propri�t� d'un objet, par exemple)
        $canEval=true;

        // Indique si l'expression pass�e en param�tre �tait encadr�e par des accolades
        $addCurly=false;

        // Indique si on est � l'int�rieur d'une chaine de caract�res ou non
        $inString=false;

        // Compteur utilis� pour g�rer l'op�rateur ternaire (xx?yy:zz).
        // Incr�ment� lorsqu'on rencontre un signe '?', d�cr�ment� lorsqu'on rencontre un signe ':'.
        // Lorsqu'on rencontre le signe ':' et que $ternary est � z�ro, c'est que c'est un collier d'expressions
        $ternary=0;

        // Indique si l'expression est un collier d'expressions ($x:$y:$z)
        // Passe � true quand on rencontre un ':' et que ternary est � z�ro.
        // Utilis� en fin d'analyse pour "enrober" l'expression finale avec le code n�cessaire
        $colon=false;

        $curly=0; // TODO : utile ? � conserver ?


        // Si $expression est un tableau de tokens, on ajoute juste T_END � la fin
        if (is_array($expression))
        {
            $tokens=$expression;
            $tokens[]=array(self::T_END,null);
        }

        // Sinon, on tokenise l'expression
        else
        {
            if (strlen($expression) && $expression[0]==='{')
            {
                $expression=substr($expression, 1, -1);
                $addCurly=true;
            }
            $tokens=self::tokenize($expression);
        }
        //self::dumpTokens($tokens);
        // Examine tous les tokens les uns apr�s les autres
        for ($index=0; $index<count($tokens); $index++)
        {
            $token=$tokens[$index];
            switch ($token[0])
            {
                case self::T_CHAR:
                    switch ($token[1])
                    {
                        // D�but/fin de chaine de caract�re contenant des variables
                        case '"':
                            $inString=! $inString;
                            break;

                        case '}':
                            //if ($curly) $tokens[$index]=null;
                            --$curly;
                            break;

                        // Remplace '!' par '->' sauf s'il s'agit de l'op�rateur 'not'
                        case '!':
                            if ($index>0)
                            {
//                                echo '<h1>Signe ! entre</h1>';
//                                self::dumpTokens(array($tokens[$index-1]));
//                                self::dumpTokens(array($tokens[$index+1]));
//                                echo '<hr />';
//
                                // Si ce qui suit est un T_STRING
                                if ($tokens[$index+1][0] == T_STRING)
                                {
                                    // et que ce qui pr�c�de est autre chose que :
                                    switch($tokens[$index-1][0])
                                    {
                                        case T_BOOLEAN_AND:
                                        case T_BOOLEAN_OR:
                                        case T_LOGICAL_AND:
                                        case T_LOGICAL_OR:
                                        case T_LOGICAL_XOR:
                                        //case T_VARIABLE:
                                            break;
                                        case self::T_CHAR:
                                            switch($tokens[$index-1][1])
                                            {
                                                case '!':
                                                case '(':
                                                case '[':
                                                    break 2;
                                            }

                                        // OK, on peut remplacer
                                        default:
                                            $tokens[$index][0]=T_OBJECT_OPERATOR;
                                            $tokens[$index][1]='->';
                                    }
                                }
                            }
                            break;

                        // L'op�rateur '=' (affectation) n'est pas autoris�
                        case '=':
                            throw new Exception('affectation interdite dans une expression, utilisez "=="'.$expression);

                        // Symbole '?' : m�morise qu'on est dans un op�rateur ternaire (xx?yy:zz)
                        case '?':
                            $ternary++;
                            break;

                        // Symbole ':' : op�rateur ternaire ou collier d'expression ($x:$y:$z)
                        case ':':
                            if ($ternary>0)     // On a un '?' en cours
                            {
                                $ternary--;
                            }
                            else                // Aucun '?' en cours : c'est le d�but ou la suite d'un collier d'expressions
                            {
                                $tokens[$index][1]=') OR $tmp=(';
                                $colon=true;
                            }
                            break;

                        case '`':
                            throw new Exception("L'op�rateur d'ex�cution est interdit dans une expression");
                        case '@':
                            throw new Exception("L'op�rateur de suppression des messages d'erreur est interdit dans une expression");
                    }
                    break;

                // Gestion/instanciation des variables : si on a un callback, on l'appelle
                case T_VARIABLE:
                    if (is_null($varCallback))
                        $canEval=false;
                    else
                    {
                        $var=$token[1];
                        $isCode=TemplateCompiler::$varCallback($var, $inString, $canEval); // TODO : ne pas coder TemplateCompiler en dur!!!

                        if ($inString)
                        {
                            if ($isCode) // il faut stopper la chaine, concat�ner l'expression puis la suite de la chaine
                                $t=array(array(self::T_CHAR,'"'), array(self::T_CHAR,'.'), array(self::T_CHAR,$var), array(self::T_CHAR,'.'), array(self::T_CHAR,'"'));
                            else // il faut ins�rer la valeur telle quelle dans la chaine
                                $t=array(array(self::T_CHAR,addslashes($var)));
                        }
                        else
                        {
                            if ($isCode)
                                $t=array(array(self::T_CHAR,$var));
                            else
                                $t=array(array(T_CONSTANT_ENCAPSED_STRING, '\''.addslashes($var). '\''));
//                                $t=array(array(self::T_CHAR,$var));
                        }

                        array_splice($tokens, $index, 1, $t);

                        if ($isCode) $canEval=false;
                    }
                    break;

                case T_CURLY_OPEN: // une { dans une chaine . exemple : "nom: {$h}"
                    //$tokens[$index][1]=null;
                    $curly++;
                    break;

                // un identifiant : appel de fonction, constante, propri�t�
                case T_STRING:  // un identifiant de fonction, de constante, de propri�t�, etc.
                case T_ARRAY:   // array(xxx), g�r� comme un appel de fonction
                case T_EMPTY:   // empty(xxx), g�r� comme un appel de fonction
                case T_ISSET:   // isset(xxx), g�r� comme un appel de fonction

                    // Appel de fonction
                    if ($tokens[$index+1][1]==='(')
                    {
                        $canEval &= self::parseFunctionCall($tokens, $index, $varCallback, $pseudoFunctions);
                        break;
                    }

                    // Si c'est une constante d�finie, on peut �valuer
                    if (defined($tokens[$index][1])) break;

                    // C'est autre chose (une propri�t�, etc.), on ne peut pas �valuer
                    $canEval=false;
                    break;

                case T_DOUBLE_ARROW: // seulement si on est dans un array()
                case T_NEW: // autoris�, pour permettre des new TextTable() et autres... mais c'est dommage
                    $canEval=false;
                    break;

                // R��criture des chaines � guillemets doubles en chaines simples si elle ne contiennent plus de variables
                case T_CONSTANT_ENCAPSED_STRING:
//                    $tokens[$index][1]=var_export(substr($token[1], 1, -1),true);
                    $tokens[$index][1]=var_export(self::evalExpression($token[1]), true);
                    break;

                // Autres tokens autoris�s, mais sur lesquels on ne fait rien
                case T_NUM_STRING:
                case self::T_END:
                case T_WHITESPACE:

                case T_BOOLEAN_AND:
                case T_BOOLEAN_OR:
                case T_LOGICAL_AND:
                case T_LOGICAL_OR:
                case T_LOGICAL_XOR:

                case T_LNUMBER:
                case T_DNUMBER:

                case T_SL:
                case T_SR:

                case T_IS_EQUAL:
                case T_IS_GREATER_OR_EQUAL:
                case T_IS_IDENTICAL:
                case T_IS_NOT_EQUAL:
                case T_IS_NOT_IDENTICAL:
                case T_IS_SMALLER_OR_EQUAL:

                case T_ARRAY_CAST:
                case T_BOOL_CAST:
                case T_INT_CAST:
                case T_DOUBLE_CAST:
                case T_INT_CAST:
                case T_OBJECT_CAST:
                case T_STRING_CAST:
                case T_UNSET_CAST:

                case T_DOUBLE_COLON:
                case T_OBJECT_OPERATOR:

                case T_ENCAPSED_AND_WHITESPACE:

                case T_INSTANCEOF:
                    break;

                // Liste des tokens interdits dans une expression de template
                case T_AND_EQUAL:       // tous les op�rateurs d'assignation (.=, &=, ...)
                case T_CONCAT_EQUAL:
                case T_DIV_EQUAL:
                case T_MINUS_EQUAL:
                case T_MOD_EQUAL:
                case T_MUL_EQUAL:
                case T_OR_EQUAL:
                case T_PLUS_EQUAL:
                case T_SL_EQUAL:
                case T_SR_EQUAL:
                case T_XOR_EQUAL:

                case T_INC:
                case T_DEC:

                case T_INCLUDE:         // include, require...
                case T_INCLUDE_ONCE:
                case T_REQUIRE:
                case T_REQUIRE_ONCE:

                case T_IF:              // if, elsif...
                case T_ELSE:
                case T_ELSEIF:
                case T_ENDIF:

                case T_SWITCH:          // switch, case...
                case T_CASE:
                case T_BREAK:
                case T_DEFAULT:
                case T_ENDSWITCH:

                case T_FOR:             // for, foreach...
                case T_ENDFOR:
                case T_FOREACH:
                case T_AS:
                case T_ENDFOREACH:
                case T_CONTINUE:

                case T_DO:              // do, while...
                case T_WHILE:
                case T_ENDWHILE:

                case T_TRY:             // try, catch
                case T_CATCH:
                case T_THROW:

                case T_CLASS:           // classes, fonctions...
                case T_INTERFACE:
                case T_FINAL:
                case T_EXTENDS:
                case T_IMPLEMENTS:
                case T_VAR :
                case T_PRIVATE:
                case T_PUBLIC:
                case T_PROTECTED:
                case T_STATIC:
                case T_FUNCTION:
                case T_RETURN:

                case T_ECHO:            // fonctions interdites
                case T_PRINT:
                case T_EVAL:
                case T_EXIT:
                case T_DECLARE:
                case T_ENDDECLARE:
                case T_UNSET:
                case T_LIST:
                case T_EXIT:
                case T_HALT_COMPILER:

                case T_OPEN_TAG:        // d�but et fin de blocs php
                case T_OPEN_TAG_WITH_ECHO:
                case T_CLOSE_TAG:

                case T_START_HEREDOC:   // syntaxe heredoc >>>
                case T_END_HEREDOC:

                case T_CHARACTER:
                case T_BAD_CHARACTER:

                case T_CLONE:
                case T_CONST:
                case T_DOLLAR_OPEN_CURLY_BRACES:

                case T_FILE:        // � g�rer : __FILE__
                case T_LINE:        // __LINE__
                case T_FUNC_C:
                case T_CLASS_C:

                case T_GLOBAL:
                case T_INLINE_HTML:

                case T_STRING_VARNAME:
                case T_USE :

             // case T_COMMENT: // inutile : enlev� durant la tokenisation
             // case T_DOC_COMMENT: // idem
             // case T_ML_COMMENT: php 4 only
             // case T_OLD_FUNCTION: php 4 only
             // case T_PAAMAYIM_NEKUDOTAYIM:

//                default:
                    throw new Exception('Interdit dans une expression : "'. $token[1]. '" ('.token_name($token[0]));

                // Tokens inconnus ou non g�r�s
                default:
                     //echo $token[0], '-', token_name($token[0]),'[',$token[1],']', "<br />";
                     self::dumpTokens($tokens);

            }
        }

        if ($colon)
        {
            array_unshift($tokens, array(self::T_CHAR, '($tmp=('));
            $tokens[]=array(self::T_CHAR, '))?$tmp:null');
        }

        $expression=self::unTokenize($tokens);

        if ($canEval)
            $expression=Utils::varExport(self::evalExpression($expression),true);
        elseif ($addCurly)
            $expression='{'.$expression.'}';

        return $canEval;
    }

    /**
     * Analyse et ex�cute un appel de fonction pr�sent dans l'expression
     */
    private static function parseFunctionCall(& $tokens, $index, $varCallback, $pseudoFunctions)
    {
        $function=$tokens[$index][1];

        // Fonctions qui peuvent �tre appell�es lors de la compilation
        static $compileTimeFunctions=null;

        // Fonctions autoris�es mais qui ne doivent �tre appell�es que lors de l'ex�cution du template
        static $runtimeFunctions=null;

        if (is_null($compileTimeFunctions))
        {
            $compileTimeFunctions=array_flip(array
            (
                'addslashes',
                'array_combine',
                'array_diff_key',
                'array_filter',
                'array_flip',
                'array_intersect_key',
                'array_map',
                'array_merge',
                'array_reverse',
                'array_slice',
                'array_sum',
                'basename',
                'chr',
                'count',
                'crc32',
                'dechex',
                'dirname',
                'empty',
                'explode',
                'get_class',
                'gettype',
                'htmlentities',
                'htmlspecialchars',
                'implode',
                'in_array',
                'is_array',
                'is_null',
                'is_scalar',
                'is_string',
                'isset',
                'iterator_to_array',
                'ltrim',
                'max',
                'md5',
                'min',
                'nl2br',
                'number_format',
                'preg_match',
                'print_r',
                'rtrim',
                'sprintf',
                'str_repeat',
                'str_replace',
                'strip_tags',
                'stripos',
                'strlen',
                'strpbrk',
                'strpos',
                'strtolower',
                'strtoupper',
                'strtr',
                'substr',
                'trim',
                'ucfirst',
                'urlencode',
                'utf8_encode',
                'var_dump',
                'var_export',
                'wordwrap',
            ));

            $runtimeFunctions=array_flip(array
            (
                'array',
                'array_keys',
                'class_exists',
                'current',
                'date',
                'extension_loaded',
                'file_get_contents',
                'filectime',
                'filemtime',
                'filesize',
                'htmlspecialchars_decode', // laisse en fonction runtime pour permettre de g�n�rer des commentaires xml dans un template
                'is_dir',
                'is_file',
                'is_link',
                'json_encode',
                'range',
                'reset',
                'strftime',
                'strtotime',
                'time',
            ));
        }

        // D�termine si cette fonction est autoris�e et ce � quoi on a affaire
        $handler=strtolower($function);    // autorise les noms de fonction aussi bien en maju qu'en minu
        $canEval=true;
//        if (isset($pseudoFunctions[$handler]))

//if ($tokens[$index][1]==='TextTable')
//{
//	self::dumpTokens(array_slice($tokens, $index-2));
//}

        // les m�thodes statiques (::) ou d'objet (->) sont toutes autoris�es
        if (isset($tokens[$index-1]) && ($tokens[$index-1][0]===T_DOUBLE_COLON || $tokens[$index-1][0]===T_OBJECT_OPERATOR))
        {
            $functype=2;
            $canEval=false;
        }
        // les new XXX() ressemble � des fonctions, sont autoris�s
        elseif (
                    (isset($tokens[$index-1]) && $tokens[$index-1][0]===T_NEW)
                ||
                    (isset($tokens[$index-2]) && $tokens[$index-1][0]===T_WHITESPACE && $tokens[$index-2][0]===T_NEW)
                )
        {
            $functype=2;
            $canEval=false;

        }
        elseif ($pseudoFunctions && array_key_exists($handler, $pseudoFunctions))
        {
            if (is_null($pseudoFunctions[$handler])) // pseudo fonction autoris�e mais handler==null
            {
                $functype=2;
                $canEval=false;
            }
            else
            {
                $handler=$pseudoFunctions[$handler];
                $functype=0;
            }
        }
        elseif (isset($compileTimeFunctions[$handler]))
        {
            $functype=1;
        }
        elseif (isset($runtimeFunctions[$handler]))
        {
            $functype=2;
            $canEval=false;
        }
        else
            throw new Exception($function.' : fonction inconnue ou non autoris�e');

        // Extrait chacun des arguments de l'appel de fonction
        $level=1;
        $args=array();
        $start=$index+2;
        for ($i=$start; $i<count($tokens); $i++)
        {
            switch ($tokens[$i][1])
            {
                case '(':
                    $level++;
                    break;
                case ')':
                    --$level;
                    if ($level===0)
                    {
                        if ($i>$start)
                        {
                            $arg=array_slice($tokens, $start, $i-$start);
                            $canEval &= self::parseExpression($arg, $varCallback, $pseudoFunctions); // pas de shortcircuit avec un &=
                            $args[]=$arg;
                        }
                        break 2;
                    }
                    break ;
                case ',':
                    if ($level===1)
                    {
                        $arg=array_slice($tokens, $start, $i-$start);
                        $canEval &= self::parseExpression($arg, $varCallback, $pseudoFunctions); // pas de shortcircuit avec un &=
                        $args[]=$arg;
                        $start=$i+1;
                    }
            }
        }
        if ($i>=count($tokens)) throw new Exception(') attendue');

        if ($canEval)
        {
            // Evalue chacun des arguments
            foreach ($args as & $arg)
                $arg=self::evalExpression($arg);

            // Appelle la fonction
            $result=call_user_func_array($handler, $args); // TODO : gestion d'erreur

            // G�n�re le code PHP du r�sultat obtenu
            $result=Utils::varExport($result, true);

            // Remplace les tokens codant l'appel de fonction par un token unique contenant le r�sultat
            array_splice($tokens, $index, $i-$index+1, array(array(self::T_CHAR,$result)));
        }
        else
        {
            if ($functype===0)
                throw new Exception('Les arguments de la pseudo-fonction '.$function.' doivent �tre �valuables lors de la compilation');

            $t=array();
            foreach ($args as $no=>$arg)
            {
                if ($no>0) $t[]=array(self::T_CHAR, ',');
                $t[]=array(self::T_CHAR, $arg);
            }
            array_splice($tokens, $index+1+1, $i-$index+1-1-1-1, $t);
        }
        return $canEval;
    }


}
?>
