<?php
namespace Fab\Document;

use Fab\Schema\Field;

use Fab\Schema\BaseNode;
use Fab\Schema\Group;
use Fab\Schema\Exception\NotFound;
use \IteratorAggregate, \ArrayIterator, \ArrayAccess, \Countable, \Serializable;
use \InvalidArgumentException;

class FieldList implements IteratorAggregate, ArrayAccess, Countable, Serializable
{
    /**
     * Le contenu du champ.
     *
     * Pour un champ simple, data[i] contient :
     * - soit un scalaire (si le champ est non répétable)
     * - soit un tableau de scalaires (si le champ est répétable)
     *
     * Pour un groupe de champs, data[i] contient :
     * - soit un objet FieldList (si le champ est non répétable)
     * - soit un tableau de FieldList (si le champ est répétable)
     *
     * @var mixed
     */
    protected $data = array();

    /**
     * L'objet Group (au sein du schéma) schéma auquel ce champ est rattaché.
     *
     * Pour un objet Document, il s'agit du schéma de la base.
     * Pour un objet FieldList, il s'agit de l'objet Group correspondant.
     *
     * @var Group
     */
    protected $node;


    /**
     * Crée un nouveau groupe de champs/
     *
     * @param Group $group l'objet Group (au sein du schéma) auquel est rattaché ce
     * groupe de champs.
     *
     * @param array $data les données initiales du document sous la forme d'un tableau
     * de la forme "nom de champ" => "contenu du champ".
     */
    public function __construct(Group $group, array $data)
    {
        $this->node = $group;
        $this->setData($data);
    }

    /**
     * Initialise les données de la liste de champs.
     *
     * @param array $data Un tableau contenant les nouvelles données.
     *
     * Le tableau peut être sous la forme "nom de champ"=> "valeur" ou sous la forme
     * "id du champ" => "valeur".
     *
     * @throws InvalidArgumentException si les données sont invalides par rapport au schéma.
     */
    public function setData(array $data)
    {
        if (! is_array($data) || key($data) === 0)
        {
            throw new InvalidArgumentException('Tableau de champs attendu');
        }

        foreach($data as $name => $value)
        {
            $this->set($name, $value);
        }
    }

    /**
     * Retourne les données du document sous la forme d'un tableau.
     * @param bool $withId Par défaut les clés du tableau retourné contiennent
     * les noms des champs (tels que ceux-ci figurent dans le schéma).
     *
     * Lorsque $withId est à true, les clés du tableaux retourné contiennent les
     * ID des champs.
     *
     * @return array
     */
    public function getData($withId = false)
    {
        $data = array();

        $key = $withId ? '_id' : 'name';
        $fields = $this->node->get('fields');
        foreach($this->data as $name => $value)
        {
            $field = $fields->get($name);

            if ($field instanceof Group)
            {
                if (is_array($value))
                {
                    foreach($value as & $item)
                    {
                        $item = $item->getData($withId);
                    }
                    if (count($value) === 1) $value = reset($value);
                }
                else
                {
                    $value = $value->getData($withId);
                }
            }
            else
            {
                //if (is_array($value) && count($value) === 1) $value = reset($value);
            }
            $data[$field->get($key)] = $value;
        }
        return $data;
    }

    /**
     * Retourne le champ du schéma dont le nom est indiqué indiqué.
     * Génère une exception s'il n'existe pas.
     *
     * @param string $name
     *
     * @return Field
     *
     * @throws NotFound si le champ n'existe pas
     */
    protected function getField($name)
    {
        if (is_null($field = $this->node->fields->get($name)))
        {
            if ($this->node instanceof Group)
            {
                throw new NotFound("La zone $name n'existe pas dans le champ " . $this->node->name . '.');
            }
            else
            {
                throw new NotFound("Le champ $name n'existe pas.");
            }
        }
        return $field;
    }


    // Méthodes de base : has/get/set/delete

    /**
     * Indique si le champ indiqué figure dans la liste.
     *
     * @param string|int $name le nom ou l'ID du champ à tester.

     * @return bool
     *
     * @throws NotFound si le champ indiqué n'existe pas dans le schéma.
     */
    public function has($name)
    {
        return isset($this->data[$this->getField($name)->name]);
    }

    /**
     * Retourne le contenu du champ indiqué.
     *
     * @param string|int $name le nom ou l'ID du champ à retourner.
     *
     * @return mixed Retourne le contenu du champ.
     *
     * Pour un champ simple, le contenu du champ est retourné sous la forme d'un
     * scalaire (string, int, bool...) ou d'un tableau de scalaires si le champ est
     * répétable.
     *
     * Pour un groupe de champ, la méthode retourne un objet FieldList ou un tableau
     * d'objets FieldList si le champ est répétable.
     *
     * @throws NotFound si le champ indiqué n'existe pas dans le schéma.
     */
    public function get($name)
    {
        $field = $this->getField($name);
        return isset($this->data[$field->name]) ? $this->data[$field->name] : null;
    }

    /**
     * Modifie le contenu d'un champ.
     *
     * @param string|int $name le nom ou l'ID du champ à modifier.
     * @param mixed $value le nouveau contenu du champ.
     *
     * @return FieldList $this.
     *
     * @throws NotFound si le champ indiqué n'existe pas dans le schéma.
     *
     * @throws InvalidArgumentException si les données fournies ne sont pas valides par
     * rapport au champ tel qu'il est définit dans le schéma.
     */
    public function set($name, $value)
    {
        $field = $this->getField($name);

        if (is_null($value) || $value === array())
        {
            unset($this->data[$field->name]);
            return $this;
        }

        if ($field instanceof Group)
        {
            // value doit obligatoirement être un tableau :
            // - soit une structure (tableau dont les clés sont des noms de champs)
            // - soit un tableau de structures : array(int => array(string=>value))

            if (! is_array($value))
            {
                throw new InvalidArgumentException("Tableau de champs attendu pour le champ $field->name");
            }

            if (key($value) !== 0)
            {
                $value = new FieldList($field, $value);
                $this->data[$field->name] = $field->repeatable ? array($value) : $value;
            }
            else
            {
                if (! $field->repeatable) throw new InvalidArgumentException("Le champ $field->name n'est pas répétable.");
                foreach($value as & $item) $item = new FieldList($field, $item);
                $this->data[$field->name] = $value;
            }
        }
        else
        {
            // value peut être
            // - soit un scalaire : valeur simple
            // - soit un tableau d'articles : tableau dont les clés sont des entiers et chaque item du tableau est un scalaire
            if (is_scalar($value))
            {
                $this->data[$field->name] = $field->repeatable ? array($value) : $value;
            }
            else
            {
                if (! $field->repeatable) throw new InvalidArgumentException("Le champ $field->name n'est pas répétable.");
                // vérifie que le tableau ne contient que des scalaires
                foreach($value as $item)
                    if (! is_scalar($item)) throw new Exception("Le champ $field->name ne peut contenir que des scalaires.");
                $this->data[$field->name] = $value;
            }
        }
        return $this;
    }

    /**
     * Supprime le contenu du champ indiqué.
     *
     * @param string|int $name le nom ou l'ID du champ à supprimer.
     *
     * @return FieldList $this.
     *
     * @throws NotFound si le champ indiqué n'existe pas dans le schéma.
     */
    public function delete($name)
    {
        $field = $this->getField($name);
        unset($this->data[$field->name]);

        return $this;
    }

    // Syntaxe $document->field : __isset/__get, __set, __unset
    // @todo: faire une fois pour toute une inteface "ObjectAccess" qui documente __get, __set, etc.

    public function __isset($name)
    {
        return $this->has($name);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        return $this->set($name, $value);
    }

    public function __unset($name)
    {
        return $this->delete($name);
    }

    // Interface ArrayAccess

    public function offsetExists($name)
    {
        return $this->has($name);
    }

    public function offsetGet($name)
    {
        return $this->get($name);
    }

    public function offsetSet($name, $value)
    {
        return $this->set($name, $value);
    }

    public function offsetUnset($name)
    {
        return $this->delete($name);
    }

    // Interface IteratorAggregate

    /**
     * Implémente l'interface {@link IteratorAggregate}.
     *
     * Permet d'itérer sur les champs du document avec une boucle foreach.
     *
     * @return ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->data);
    }

    // Interface Countable

    public function count()
    {
        return count($this->data);
    }

    // Interface Serialize

    public function serialize()
    {
        return serialize($this->getData(true));
    }

    public function unserialize($data)
    {
        $this->setData(unserialize($data));
    }

    // Débogage et affichage

    public function __toString()
    {
        $h = '{';
        foreach($this as $name=>$value)
        {
            $h .= "$name:";

            if (is_array($value))
                $h .= '[' . implode(', ', $value) . ']';
            else
                $h .= $value;

            $h .= ', ';
        }
        $h = rtrim($h, ', ');
        $h .= '}';
        return $h;
    }

    public function dump($indent = 0)
    {
        $tab1 = str_repeat(' ', $indent);
        $tab2 = str_repeat(' ', $indent+4);
        $tab3 = str_repeat(' ', $indent+8);

        echo $tab1, substr(get_class($this), 18), "\n$tab1(\n";
        foreach($this->data as $field => $value)
        {
            echo "$tab2$field=" ;
            if (is_array($value))
            {
                echo "array$tab2\n$tab2(\n";
                foreach($value as $item)
                {
                    if ($item instanceof FieldList) $item->dump($indent+8); else echo $tab3,var_export($item,true), ",\n";

                }
                echo "$tab2)\n";
            }
            else
            {
                if ($value instanceof FieldList) $value->dump($indent+4); else echo var_export($value,true), ",\n";
            }
        }
        echo "$tab1),\n";
    }

    protected function validate($data)
    {
        $result = array();
        foreach($data as $name => $value)
        {
            $field = $this->node->fields->get($name);
            if (is_null($field))
            {
                throw new NotFound("Le champ $name n'existe pas.");
            }

            $type = ($field instanceof Group) ? 'FieldList' : ($field->type . 'Field');
            echo "name=$name, Type=$type<br />";
            $class = __NAMESPACE__ . '\\' . $type;
            $result[$field->name] = new $class($field, $value);
        }
        return $result;
    }
}