<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Représente un enregistrement au format Bdsp.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel Ménard <Daniel.Menard@ehesp.fr>, Séverine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */
class BdspRecord extends Record
{
    /**
     * Liste des champs du format Bdsp96
     *
     * @var array|Multimap
     */
    protected $format = array
    (
        'Ref',      // No de référence de l'enregistrement
        'Typdoc',   // Type de document principal
        'TypdocB',  // Type de document secondaire
        'AutPhys',  // Auteurs physiques
        'AutColl',  // Auteurs collectifs. Ajouter " / com." pour un commanditaire
        'TitorigM', // Titre original du document au niveau monographique
        'TitorigA', // Titre original du document au niveau analytique
        'TitFran',  // Traduction en français de TITORIGM ou TITORIGA
        'TitEng',   // Traduction en anglais de TITORIGM ou TITORIGA
        'TitCol',   // Titre de la collection
        'Diplom',   // Intitulé du diplôme
        'TitCong',  // Titre original du congrès
        'TitCongF', // Traduction en français de TITCONG
        'VilCong',  // Ville du congrès
        'DatEdit',  // Date d'édition sous la forme AAAA/MM/JJ
        'DatCong',  // Date de tenue du congrès
        'TitPerio', // Titre développé du périodique
        'NoVol',    // Numéro de volume, ou tomaison
        'NoFasc',   // Numéro de fascicule
        'NumDiv',   // Mention d'édition, numéro spéciaux, législation, etc.
        'PageColl', // Pagination et mentions de collation
        'RefBib',   // Références bibliographiques
        'Issn',     // Numéro ISSN (identificateur du périodique)
        'VilEd',    // Editeurs sous la forme Ville : Nom d'éditeur
        'Isbn',     // Numéro ISBN (identificateur de l'ouvrage)
        'CodPays',  // Pays d'édition du document, code ISO 3 lettres
        'CodLang',  // Langue du document, code ISO 3 lettres
        'LangResu', // Langue du résumé qui figure dans le champ RESUM
        'Resum',    // Résumé du document
        'MotsCles', // Mots-clés BDSP
        'NouvDesc', // Candidats descripteurs
        'CodInist', // Cote dans le plan de classement INIST
        'Ident',    // Sigle du producteur et cote d'accès au document
//      'Period',   // Periode couverte (si impossible à mettre dans MOTSCLES)
        'Adr',      // Adresse complète du document en texte intégral

    );


    /**
     * Séparateur utilisé pour les champs articles
     *
     * @var string
     */
    protected $sep = '· '; // chr(0183)


    /**
     * Surcharge la méthode héritée de Record : dans le cas d'un enregistrement
     * au format BDSP, le convertir au format BDSP n'a pas de sens.
     *
     * La méthode se contente de retourner <code>$this</code>.
     *
     * @return $this
     */
    public function toBdsp()
    {
        return $this;
    }
}