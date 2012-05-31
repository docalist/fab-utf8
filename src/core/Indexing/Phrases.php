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

use Fab\Indexing\AnalyzerInterface;

/**
 * Indexe les mots du texte et stocke leur position pour permettre
 * la recherche par phrase (expression entre guillemets) et la
 * recherche de proximité (opérateur NEAR).
 *
 * Les caractères [a-z0-9@_] sont utilisés pour découper le texte en mots.
 * Tous les autres caractères sont ignorés.
 *
 * Les sigles de 2 à 9 lettres sont convertis en mots.
 *
 * Cet analyseur ne fonctionne que sur du texte préalablement convertit en
 * minuscules non accentuées : dans votre chaine d'analyse, vous devez au
 * préalable utiliser un analyseur tel que {@link \Fab\Indexing\Lowercase}
 * ou {@link \Fab\Indexing\StripTags}.
 */
class Phrases extends Words
{
    protected static $destination = 'postings';
}