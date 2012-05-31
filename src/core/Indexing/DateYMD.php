<?php
/**
 * This file is part of the Fab package.
 *
 * For copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 *
 * @package     Fab
 * @subpackage  Indexing
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id$
 */
namespace Fab\Indexing;

/**
 * Indexe un champ date contenant une date stockée au format
 * "année mois jour" (AAAAMMJJ).
 *
 * Cet analyseur ajoute les termes suivants :
 * - le numéro de jour,
 * - le nom du mois (en français, en anglais et sous forme abbrégée),
 * - l'année.
 *
 * Cet analyseur peut être utilisé tout seul : il n'y a pas besoin d'appliquer au
 * préalable un tokenizer tel que {@link Lowercase}.
 */
class DateYMD implements AnalyzerInterface
{
    /**
     * Version tokenisée des noms de mois, en français, en anglais
     * et en forme longue et abrégée.
     *
     * @var array
     */
    static protected $monthes = array
    (
        '01' => array('january', 'janvier', 'janv', 'jan'),
        '02' => array('february', 'fevrier', 'fevr', 'feb', 'fev'),
        '03' => array('mars', 'mar'),
        '04' => array('april', 'avril', 'apr', 'avr'),
        '05' => array('may', 'mai'),
        '06' => array('june', 'juin', 'jun'),
        '07' => array('july', 'juillet', 'jul'),
        '08' => array('august', 'aout', 'aug', 'aou'),
        '09' => array('september', 'septembre', 'sep', 'sept'),
        '10' => array('october', 'octobre', 'oct'),
        '11' => array('november', 'novembre', 'nov'),
        '12' => array('december', 'decembre', 'dec'),
    );

    public function analyze(AnalyzerData $data)
    {
        foreach ($data->content as $value)
        {
            $value = strtr($value, array('/'=>'', '-'=>'', '(' => '', ')' => ''));

            $len = strlen($value);

            // on a seulement l'année (ou autre chose)
            if ($len <= 4)
            {
                $data->terms[] = $value;
                continue;
            }

            // AAAAMMJJ
            // 01234567

            // on a au moins l'année et le mois
            $month = substr($value, 4, 2);
            if (isset(self::$monthes[$month]))
            {
                $terms = self::$monthes[$month]; // noms des mois
            }
            else
            {
                $terms = array(); // mois invalide
            }

            array_unshift($terms, substr($value, 0, 4)); // année

            array_unshift($terms, substr($value, 0, 6)); // année et mois au format

            // on a les jours
            if ($len > 6)
            {
                array_push($terms, substr($value, 6, 2));
                array_unshift($terms, $value); // année, mois et jour au format AAAAMMJJ
            }

            $data->terms[] = $terms;
        }
    }
}