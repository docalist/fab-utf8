<?php
/**
 * This file is part of the Fooltext package.
 *
 * For copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 *
 * @package     Fooltext
 * @subpackage  Store
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id$
 */
namespace Fooltext\Store;

use \Request;
use \Exception;
use \Config;
use Fab\Schema\Schema;

class SearchRequest
{
    protected $equation = null;
    protected $start = 1;
    protected $max = 10;
    protected $sort = '-';
    protected $filter = null;
    protected $minscore = 0;
    protected $checkatleast = 100;
    protected $rset = null;
    protected $defaultop = 'AND';
//    protected $opanycase = 'true';
//    protected $defaultindex = null;

    protected $prob = array(); // conserver l'ordre : seules les propriétés au dessus peuvent figurer dans la query string
    protected $bool = array();
    protected $love = array();
    protected $hate = array();

    public function __construct($data = null, Schema $schema = null)
    {
        if (is_null($data)) return;

        if ($data instanceof Request) $data = $data->getParameters();

        foreach($this as $name=>$value)
        {
            if ($name === 'prob') break;

            if (isset($data["_$name"]))
            {
                $this->$name($data["_$name"]);
            }
            elseif (! is_null($value = Config::userGet($name)))
            {
                $this->$name($value);
            }
        }

        if (is_null($schema)) return;

        // Examine tous les paramètres
        foreach($data as $name => $value)
        {
            if ($value === null || $value === '' || $value === array()) continue;

            // Teste s'il s'agit d'un nom d'index ou d'alias existant dans le schéma
            $h = ltrim($name,'+-');
            $index = $schema->indices->get($h);
            if (is_null($index)) $index = $schema->aliases->get($h);
            if (is_null($index)) continue;

            // Détermine comment il faut analyser la requête et où la stocker
            switch(substr($name, 0, 1))
            {
                case '+':
                    $this->love[$index->name] = $value;
                    break;

                case '-':
                    $this->hate[$index->name] = $value;
                    break;

                default:
                    if (FALSE && $index->_type === Index::BOOLEAN) // ******* todo
                    {
                        $this->bool[$index->name] = $value;
                    }
                    else
                    {
                        $this->prob[$index->name] = $value;
                    }
                    break;
            }
        }
    }


    /**
     * @param string|array $value
     *
     * @return SearchRequest
     * @throws Exception
     */
    public function equation($value = null)
    {
        return $this->getset(__FUNCTION__, $value, true, 'checkString');
    }

    /**
     * @param int $value
     *
     * Explication : si on est sur la 2nde page avec max=10, on affiche
     * la 11ème réponse en premier. Si on demande alors à passer à 50
     * notices par page, on va alors afficher les notices 11 à 50, mais
     * on n'aura pas de lien "page précédente".
     * Le code ci-dessus, dans ce cas, ramène "start" à 1 pour que toutes
     * les notices soient affichées.
     *
     * @return SearchRequest
     * @throws Exception
     */
    public function start($value = null)
    {
        $start = $this->getset(__FUNCTION__, $value, false, 'checkInt');
        //
        if (is_null($value) && $this->max > 1)
        {
            $start = $start - (($start - 1) % $this->max);
        }
        return $start;
    }

    /**
     * @param int $value
     *
     * @return SearchRequest
     * @throws Exception
     */
    public function max($value = null)
    {
        return $this->getset(__FUNCTION__, $value, false, 'checkInt');
    }

    /**
     * @param string|array $value
     *
     * @return SearchRequest
     * @throws Exception
     */
    public function sort($value = null)
    {
        return $this->getset(__FUNCTION__, $value, true, 'checkString');
    }

    /**
     * @param string|array $value
     *
     * @return SearchRequest
     * @throws Exception
     */
    public function filter($value = null)
    {
        return $this->getset(__FUNCTION__, $value, true, 'checkString');
    }

    /**
     * @param int $value
     *
     * @return SearchRequest
     * @throws Exception
     */
    public function minscore($value = null)
    {
        return $this->getset(__FUNCTION__, $value, false, 'checkInt');
    }

    /**
     * @param int $value
     *
     * @return SearchRequest
     * @throws Exception
     */
    public function checkatleast($value = null)
    {
        return $this->getset(__FUNCTION__, $value, false, 'checkInt');
    }

    /**
     * @param int|array $value
     *
     * @return SearchRequest
     * @throws Exception
     */
    public function rset($value = null)
    {
        return $this->getset(__FUNCTION__, $value, true, 'checkInt');
    }

    /**
     * @param string $value
     *
     * @return SearchRequest
     * @throws Exception
     */
    public function defaultop($value = null)
    {
        return $this->getset(__FUNCTION__, $value, false, 'checkOp');
    }

    /**
     * @param bool $value
     *
     * @return SearchRequest
     * @throws Exception
     */
//     public function opanycase($value = null)
//     {
//         return $this->getset(__FUNCTION__, $value, false, 'checkBool');
//     }

    /**
     * @param string $value
     *
     * @return SearchRequest
     * @throws Exception
     */
//     public function defaultindex($value = null)
//     {
//         return $this->getset(__FUNCTION__, $value, false, 'checkString');
//     }

    public function prob()
    {
        return $this->prob;
    }

    public function bool()
    {
        return $this->bool;
    }

    public function love()
    {
        return $this->love;
    }

    public function hate()
    {
        return $this->hate;
    }

    /**
     *
     * @return SearchRequest
     */
    public function dump()
    {
        echo '<pre>';
        foreach ($this as $name => $value)
        {
            $value = $this->$name();

            echo $name, ': ';
            if (is_null($value))
            {
                echo 'null';
            }
            elseif (is_array($value))
            {
                if (key($value) === 0)
                    echo '[', implode(', ', $value), ']';
                else
                {
                    echo '[';
                    $first = true;
                    foreach ($value as $key=>$item)
                    {
                        if( ! $first) echo ', ';
                        echo $key, ':', $item;
                        $first = false;
                    }
                    echo ']';
                }
            }
            elseif (is_bool($value))
            {
                echo $value ? 'true' : 'false';
            }
            else
            {
                echo (string) $value;
            }
            echo "\n";
        }
        echo '</pre>';

        return $this;
    }

    /**
     *
     * @param string $name
     * @param mixed $value
     * @param bool $isarray
     * @param callable $check
     *
     * @return SearchRequest
     * @throws Exception
     */
    protected function getset($name, $value, $isarray = false, $check = null)
    {
        if (is_null($value)) return $this->$name;

        if ($isarray)
        {
            if (! is_array($value)) $value = array($value);

            foreach ($value as & $item)
            {
                if (! $this->$check($item))
                {
                    throw new Exception("$name : valeur incorrecte (" . var_export($item, true) . "), $check attendu.");
                }
            }
        }
        else
        {
            if (is_array($value))
            {
                throw new Exception("$name : valeur unique attendue");
            }

            if (! is_null($check) && ! $this->$check($value))
            {
                throw new Exception("$name : valeur incorrecte (" . var_export($value, true) . "), $check attendu.");
            }
        }

        $this->$name = $value;

        return $this;
    }

    private function checkInt(& $value)
    {
        if (is_int($value)) return true;
        if (is_numeric($value))
        {
            $value = (int) $value;
            return true;
        }
        return false;
    }

    private function checkBool(& $value)
    {
        if (is_bool($value)) return true;

        $t = array('true' => true, 'false' => false, 'on' => true, 'off' => false, '1' => true, '0' => false);
        $key = strtolower($value);
        if (! isset($t[$key])) return false;
        $value = $t[$key];
        return true;
    }

    private function checkString(& $value)
    {
        $value = (string) $value;
        return true;
    }

    /**
     * Ajuste start pour que ce soit un multiple de max
     *
     * Explication : si on est sur la 2nde page avec max=10, on affiche
     * la 11ème réponse en premier. Si on demande alors à passer à 50
     * notices par page, on va alors afficher les notices 11 à 50, mais
     * on n'aura pas de lien "page précédente".
     * Le code ci-dessus, dans ce cas, ramène "start" à 1 pour que toutes
     * les notices soient affichées.
     *
     * @param int $value
     * @return bool
     */
//     private function checkStart(& $value)
//     {
//         if (! $this->checkInt($value)) return false;

//         //
//         if ($this->max > 1)
//         {
//             $value = $value - (($value - 1) % $this->max);
//         }
//         return true;
//     }

    private function checkOp(& $value)
    {
        if (! $this->checkString($value)) return false;
        $value = strtolower($value);
        if ($value === 'and' || $value === 'or') return true;

        return false;
    }
}