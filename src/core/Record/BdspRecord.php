<?php
/**
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>, S�verine Ferron <Severine.Ferron@ehesp.fr>
 * @version     SVN: $Id$
 */

/**
 * Repr�sente un enregistrement au format Bdsp.
 *
 * @package     nct
 * @subpackage  common
 * @author      Daniel M�nard <Daniel.Menard@ehesp.fr>, S�verine Ferron <Severine.Ferron@ehesp.fr>
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
        'Ref',      // No de r�f�rence de l'enregistrement
        'Typdoc',   // Type de document principal
        'TypdocB',  // Type de document secondaire
        'AutPhys',  // Auteurs physiques
        'AutColl',  // Auteurs collectifs. Ajouter " / com." pour un commanditaire
        'TitorigM', // Titre original du document au niveau monographique
        'TitorigA', // Titre original du document au niveau analytique
        'TitFran',  // Traduction en fran�ais de TITORIGM ou TITORIGA
        'TitEng',   // Traduction en anglais de TITORIGM ou TITORIGA
        'TitCol',   // Titre de la collection
        'Diplom',   // Intitul� du dipl�me
        'TitCong',  // Titre original du congr�s
        'TitCongF', // Traduction en fran�ais de TITCONG
        'VilCong',  // Ville du congr�s
        'DatEdit',  // Date d'�dition sous la forme AAAA/MM/JJ
        'DatCong',  // Date de tenue du congr�s
        'TitPerio', // Titre d�velopp� du p�riodique
        'NoVol',    // Num�ro de volume, ou tomaison
        'NoFasc',   // Num�ro de fascicule
        'NumDiv',   // Mention d'�dition, num�ro sp�ciaux, l�gislation, etc.
        'PageColl', // Pagination et mentions de collation
        'RefBib',   // R�f�rences bibliographiques
        'Issn',     // Num�ro ISSN (identificateur du p�riodique)
        'VilEd',    // Editeurs sous la forme Ville : Nom d'�diteur
        'Isbn',     // Num�ro ISBN (identificateur de l'ouvrage)
        'CodPays',  // Pays d'�dition du document, code ISO 3 lettres
        'CodLang',  // Langue du document, code ISO 3 lettres
        'LangResu', // Langue du r�sum� qui figure dans le champ RESUM
        'Resum',    // R�sum� du document
        'MotsCles', // Mots-cl�s BDSP
        'NouvDesc', // Candidats descripteurs
        'CodInist', // Cote dans le plan de classement INIST
        'Ident',    // Sigle du producteur et cote d'acc�s au document
//      'Period',   // Periode couverte (si impossible � mettre dans MOTSCLES)
        'Adr',      // Adresse compl�te du document en texte int�gral

    );


    /**
     * S�parateur utilis� pour les champs articles
     *
     * @var string
     */
    protected $sep = '� '; // chr(0183)


    /**
     * Surcharge la m�thode h�rit�e de Record : dans le cas d'un enregistrement
     * au format BDSP, le convertir au format BDSP n'a pas de sens.
     *
     * La m�thode se contente de retourner <code>$this</code>.
     *
     * @return $this
     */
    public function toBdsp()
    {
        return $this;
    }
}