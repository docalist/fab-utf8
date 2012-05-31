<?php
/**
 * This file is part of the Fooltext package.
 *
 * For copyright and license information, please view the
 * LICENSE.txt file that was distributed with this source code.
 *
 * @package     Fooltext
 * @subpackage  Document
 * @author      Daniel Ménard <Daniel.Menard@laposte.net>
 * @version     SVN: $Id: AnalyzerInterface.php 10 2011-12-13 15:45:47Z daniel.menard.35@gmail.com $
 */
namespace Fab\Document;

use Fab\Schema\Schema;

class Document extends FieldList
{
    /**
     * L'objet Schema auquel ce document est rattaché.
     *
     * Remarque : on surcharge ici la propriété héritée de FieldList uniquement
     * pour pouvoir la documenter avec le bon type et bénéficier de la completion
     * automatique dans eclipse.
     *
     * @var Schema
     */
    protected $node;

    /**
     * Contruit un nouveau document.
     *
     * @param Schema $schema Le schéma auquel est rattaché ce document.
     * @param array $data les données initiales du document sous la forme d'un tableau
     * de la forme "nom de champ" => "contenu du champ".
     */
    public function __construct(Schema $schema, array $data = null)
    {
        $this->node = $schema;
        if (! is_null($data)) $this->setData($data);

        // remarque : le code de ce constructeur est identique à celui hérité
        // de FieldList. On surcharge içi uniquement pour pouvoir typer et
        // documenter correctement les paramètres.
    }

    public function __toString()
    {
        $h = "\n";
        foreach($this as $name=>$value)
        {
            $h .= $name . ': ';

            if (is_array($value))
                $h .= '[' . implode(', ', $value) . ']';
            else
                $h .= $value;

            $h .= "\n";
        }
        return $h;
    }
}