<?php
/**
 * This file is part of the Fooltext package.
 *
 * For copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 *
 * @package     Fooltext
 * @subpackage  Indexing
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id$
 */
namespace Fooltext\Indexing;

/**
 * Indexation étendue d'un champ booléen.
 *
 * Cet analyseur fonctionne comme l'analyseur de base {@link Boolean} mais génère
 * les mots-clés 'true', 'on', '1' et 'vrai' si le champ est à true et les mots-clés
 * 'false', 'off', '0', 'faux' dans le cas contraire.
 *
 * Cet analyseur peut être utilisé tout seul : il n'y a pas besoin d'appliquer au
 * préalable un tokenizer tel que {@link Lowercase}.
 */
class BooleanExtended extends Boolean
{
    /**
     * Termes à générer si le booléen est à true.
     *
     * @var mixed
     */
    protected static $true = array('true', 'on', '1', 'vrai');

    /**
     * Termes à générer si le booléen est à false.
     *
     * @var mixed
     */
    protected static $false = array('false', 'off', '0', 'faux');
}