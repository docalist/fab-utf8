<?php

/*

Points à faire valider par Prisme :
- une date de la forme "1ER TRIMESTRE 1987" est convertie en "1987-01" (i.e. janvier)

- une date de la forme "1ER SEMESTRE 1998" est convertie en "1998-01" (i.e. janvier)

 */


class PrismeReader implements Iterator
{
    /**
     * Délimiteur utilisé pour marquer les fins de ligne (les fins d'enregistrement).
     *
     * @var string
     */
    const RECORD_DELIMITER = '+++';

    /**
     * Délimiteur utilisé entre les champs.
     *
     * @var string
     */
    const FIELD_DELIMITER = ';;';

    /**
     * Longueur maximale d'un enregistrement.
     *
     * @var int
     */
    const MAX_LINE_LENGTH = 4096;


    /**
     * Path du fichier en cours.
     *
     * @var string
     */
    private $path;

    /**
     * Handle du fichier en cours.
     *
     * @var resource
     */
    protected $file;

    /**
     * Noms des champs.
     *
     * @var array
     */
    protected $headers;

    /**
     * Données de l'enregistrement en cours.
     *
     * @var array
     */
    protected $data;

    /**
     * Numéro de l'enregistrement en cours.
     *
     * @var int
     */
    protected $recordNumber;

    /**
     * Flag indiquant si la fin de fichier a été atteinte
     *
     * @var bool
     */
    protected $eof;

    /**
     * Indique s'il faut convertir les données de l'ancien format
     * (éclatement des champs Url, So, etc.)
     *
     * @var bool
     */
    protected $conversion = true;


    /**
     * Constructeur. Ouvre le fichier passé en paramètre.
     *
     * @param string $path
     */
    public function __construct($path, $conversion = true)
    {
        $this->conversion = $conversion;
        $this->path = $path;
        $this->file = fopen($path, 'r');
    }


    /**
     * Destructeur. Ferme le fichier en cours.
     */
    public function __destruct()
    {
        fclose($this->file);
    }


    /**
     * Lit une ligne du fichier et retourne un tableau contenant les données.
     *
     * @return array|false retourne un tableau contenant les données ou false si la fin
     * du fichier est atteinte (dans ce cas, la propriété $eof est mise à true).
     */
    protected function read()
    {
        if (feof($this->file))
        {
            $this->eof = true;
            return false;
        }
        $data = stream_get_line($this->file, self::MAX_LINE_LENGTH, self::RECORD_DELIMITER);
        $data = explode(self::FIELD_DELIMITER, $data);
        return $data;
    }


    /**
     * Interface Iterator. Initialise une boucle foreach.
     *
     * Charge la ligne d'entête du fichier, initialise le numéro de l'enregistrement en cours et
     * le flag de fin de fichier puis charge le premier enregistrement.
     */
    function rewind()
    {
        fseek($this->file, 0);
        $this->headers = $this->read();
        $this->recordNumber = 0;
        $this->eof = false;
        $this->next();
    }


    /**
     * Interface Iterator. Charge le prochain enregistrement du fichier.
     *
     */
    function next()
    {
        $this->data = $this->read();
        if ($this->data === false) return;

        if (count($this->headers) !== count($this->data))
        {
//            echo "<pre>Anomalie pour l'enreg n° ", $this->recordNumber, '<br />';
//            var_export($this->headers);
//            var_export($this->data);
//            echo "</pre>";
            $this->data = array_pad($this->data, count($this->headers), null);
        }

        $this->data = array_combine($this->headers, $this->data);
        ++$this->recordNumber;

        // Conversion de l'ancien format
        if ($this->conversion)
        {
            if ($this->data['URL'])
                if (! $this->splitUrl($this->data)) echo 'SO:', $this->data['URL'], '<br />';

            if ($this->data['SO'])
                if (! $this->splitSo($this->data)) ;//echo 'SO:', $this->data['SO'], '<br />';
        }
    }


    /**
     * Interface Iterator. Indique si la fin de fichier a été atteinte.
     *
     * @return bool
     */
    function valid()
    {
        return ! $this->eof;
    }


    /**
     * Interface Iterator. Retourne le numéro d'enregistrement en cours.
     *
     * @return int
     */
    function key()
    {
        return $this->recordNumber;
    }


    /**
     * Interface Iterator. Retourne les données de l'enregistrement en cours.
     *
     *  @return array
     */
    function current()
    {
        return $this->data;
    }


    /********** Conversion de l'ancien format **********/
    // Champs à éclater :
    // - URL -> (URL, DCSITE)
    // - SO  -> (VOL, SO, PER, DP)
    // - ED  -> (EDLIEU, ED, EDCOL, EDNU)
    // - NO  -> (NO, NOAN)



    /**
     * Eclate le champ URL en deux champs distincts : URL et DCSITE (date de consultation du site).
     *
     * Le champ URL d'origine est conservé sous le nom "OLDURL".
     *
     * @param array $record
     */
    protected function splitUrl(& $record)
    {
        $url = $record['URL'];
        $record['OLDURL'] = $url;

        // Eclate le champ
        $length = strcspn($url, ' ,');
        $dcsite = trim(substr($url, $length), ' ,()'); // todo: extraire la date ?
        $url = substr($url, 0, $length);

        // Supprime les mentions "AUTRE"
        if ($url !== 'AUTRE') $url = $dcsite = '';

        // Stocke le résultat
        $record['URL'] = $url;
        $record['DCSITE'] = $dcsite;

        return true;
    }


    /**
     * Eclate le champ SO en quatre champs distincts : VOL, SO, PER et DP.
     *
     * Le champ SO d'origine est conservé sous le nom "OLDSO".
     *
     * @param array $record
     */
    protected function splitSo(& $record)
    {
        $h = $record['SO'];
        $record['OLDSO'] = $h;
        $record['VOL'] = $record['SO'] = $record['PER'] = $record['DP'] = '';

//        // N.2149, 2000
//        if (preg_match('~^N\.\s*(\d+)\s*,\s*(\d{4})$~', $h, $match)) // 1. numéro, 2. année
//        {
//            $record['SO'] = 'n°' . $match[1];
//            $record['DP'] = $match[2];
//            return true;
//        }

//        // N.2149, 4 TRIMESTRE 2003
//        // N.2149, 1ER SEMESTRE 2003
//        // N.2149, 1 SEMESTRE 2003
//        if (preg_match('~^N\.\s*(\d+(?:-\d+)?)\s*,\s*(\d+)(?:ER)?\s+(TRIMESTRE|SEMESTRE)\s+(\d+)$~', $h, $match)) // 1.numéro, 2.jour, 3.trimestre, 4.année
//        {
//            // convertit le trimestre en mois
//            switch ($match[3])
//            {
//                case 'TRIMESTRE':
//                    switch ($match[2])
//                    {
//                        case '1': $month = '01'; break;
//                        case '2': $month = '04'; break;
//                        case '3': $month = '07'; break;
//                        case '4': $month = '10'; break;
//                        default: die('numéro de trimestre non géré');
//                    }
//                    break;
//
//                case 'SEMESTRE':
//                    switch ($match[2])
//                    {
//                        case '1': $month = '01'; break;
//                        case '2': $month = '06'; break;
//                        default: die('numéro de semestre non géré');
//                    }
//                    break;
//            }
//
//            $record['SO'] = 'n°' . $match[1];
//            $record['DP'] = $match[4] . '-' . $month;
//            return true;
//        }

        // N.2149, 14 JANVIER 2000
        // N.2149, 1ER DECEMBRE 2000
        // N.2149, JANVIER 2000
        //if (preg_match('~^(?:(?:VOL|VOLUME|T|TOME)[\. ]+([\dIVXLC-]+)\s*,\s*)?(SUPPLEMENT AU )?N\.\s*(\d+(?:-\d+)?)\s*,\s*(?:(\d+)(?:ER)?\s+)?([\w-]+)\s+(\d+)$~', $h, $match)) // 1. Volume 2. supplément, 3.numéro, 4.jour, 5.mois, 6.année

        $re =
        '~^
            # Numéro de volume, numéro de tome, etc
            (?P<V1>
                # Mention de volume
                (?:
                    VOL|VOLUME|T|TOME
                    |\d+(?:ERE|EME)\ ANNEE
                )
                [\. ]?

                # Numéro de volume
                (?P<vol>
                    [\dIVXLC-]+                             # $vol : numéro de volume
                )?

                # virgule
                \s*,\s*
            )?

            # Numéro de fascicule
            (?P<fullnum>
                # Libellé du numéro
                (?P<labelnum>                               # labelnum : libellé du numéro
                    N\.
                    |SUPPLEMENT\ AU\ N\.
                    |SUPPL.\ AU\ N\.
                    |HORS-SERIE\ N\.
                    |HS\ N\.
                    |N.\s*HORS\s*SERIE
                    |CAHIER\ N\.
                )?
                \s*

                # Numéro
                (?<num>
                    \d+(?:\s*-\s*\d+)*                      # num : numéro de fascicule
                    (?:,\s*\d+,\s*)?
                )?

                # seconde mention
                (?:
                    \s*HS|\s*SPECIAL
                )?

                # Virgule
                [, ]*
            )?

            # Numéro de tome (si pas à sa place au début)
            (?P<V2>
                # Mention de volume
                (?:
                    TOME
                )
                [\. ]?

                # Numéro de volume
                (?P<vol2>
                    [\dIVXLC-]+                             # $vol2 : second numéro de tome
                )?

                # virgule
                [, ]*
            )?

            # Date
            (?P<date>
                (?:
                    # Jour
                    (?:
                        (?P<day>\d+)                        # $day : jour
                        (?:ER)?
                        \s+
                    )?

                    # Mois
                    (?P<month>                              # $month : mois
                        [\w /-]+
                        ,?
                    )
                    [ \.]*
                )?

                # Année
                (?P<year>\d+)                               # $year : année
            )

            # Vol3 ?
            (?P<V3>
                ,\s*
                (?P<vol3>
                    [\w\d+/-]+
                )
            )?
        $~x';

        if (preg_match($re, $h, $match))
        {
            extract($match); // $vol, $labelnum, $num, $day, $month, $year
            if (! isset($V3)) $V3='';
            //echo "[$V1] [$fullnum] [$V2] [$date] [$V3]<br />";
            echo "<tr><td>$V1</td><td>$fullnum</td><td>$V2</td><td>$date</td><td>$V3</td></tr>";

            $record['SO'] = ($labelnum ? 'Supplément au ' : '') . 'n°' . $num;

            if ($vol)
                $record['VOL'] = 'Vol. ' . $vol;

            if ($day)
                $day = substr('0'.$day, -2);

            $record['DP'] = $year . '-' . $this->convertMonth($month) . $day;

            return true;
        }

        // on n'a pas réussi à convertir
        $record['SO'] = $record['OLDSO'];
        unset($record['OLDSO']);

        return false;
    }


    /**
     * Convertit un nom de mois en numéro.
     *
     * @param string $h le nom du mois à convertir
     *
     * @return string le nom du mois sur deux chiffres, sous la forme d'une chaine contenant
     * les zéros initiaux éventuellement nécessaires.
     */
    private function convertMonth($h)
    {
        $monthes = array
        (
            // Mensuels
            'JANVIER'   => '01', //'1' => '01',
            'FEVRIER'   => '02',
            'MARS'      => '03',
            'AVRIL'     => '04',
            'MAI'       => '05',
            'JUIN'      => '06',
            'JUILLET'   => '07',
            'AOUT'      => '08',
            'SEPTEMBRE' => '09', 'SEPT'      => '09',
            'OCTOBRE'   => '10', '0CTOBRE'   => '10', // avec un zéro au début
            'NOVEMBRE'  => '11', 'NOV'       => '11',
            'DECEMBRE'  => '12',

            // Bimensuels
            'JANVIER-FEVRIER'   => '01', 'JANV-FEVRIER' => '01',
            'FEVRIER-MARS'      => '02',
            'MARS-AVRIL'        => '03',
            'AVRIL-MAI'         => '04',
            'MAI-AVRIL'         => '04', // A l'envers !
            'MAI-JUIN'          => '05',
            'JUIN-JUILLET'      => '06',
            'JUILLET-AOUT'      => '07', 'JUILLET-AOÛT' => '07', 'JUIL-AOUT' => '07', 'JULLET-AOUT' => '07', // Typo
            'AOUT-SEPTEMBRE'    => '08',
            'SEPTEMBRE-OCTOBRE' => '09',
            'OCTOBRE-NOVEMBRE'  => '10', 'OCTOBE-NOVEMBRE' => '10', // TYPO
            'NOVEMBRE-DECEMBRE' => '11', 'NOV-DEC' => '11', 'NOV-DECEMBRE' => '11', 'NOVEBMRE-DECEMBRE' => '11', // TYPO

            // Trimestriels
            'JANVIER-MARS'      => '01', 'JANV-MARS' => '01', 'JANVIER-FEVRIER-MARS' => '01',
            'FEVRIER-AVRIL'     => '02',
            'AVRIL-JUIN'        => '04', 'AVRIL-MAI-JUIN' => '04',
            'MAI-JUIN-JUILLET'  => '05',
            'JUIN-AOUT'         => '06', 'JUIN-JUILLET-AOUT' => '06',
            'JUILLET-SEPTEMBRE' => '07', 'JUILLET-AOUT-SEPTEMBRE'    => '07', 'JUILLET-SEPT' => '07',
            'AOUT-OCTOBRE'      => '08',
            'SEPTEMBRE-NOVEMBRE'=> '09',
            'OCTOBRE-DECEMBRE'  => '10', 'OCTOBRE-NOVEMBRE-DECEMBRE' => '10',
            'DECEMBRE-JANVIER'  => '12', // !!! ambigu dec-jan 2000 : dec 2000 ou jan 2000 ?

            // Semestriels
            'JANVIER-JUIN'      => '01', 'JANV-JUIN' => '06',
            'JUILLET-DECEMBRE'  => '07',

            // Périodes diverses
            'JANVIER-JUILLET'   => '01',
            'JANVIER-AVRIL'     => '01',
            'JANVIER-MAI'       => '01',
            'FEVRIER-MAI'       => '02',
            'MARS-MAI'          => '03', 'MARS-AVRIL-MAI' => '03',
            'MARS-JUIN'         => '03', 'MARS-AVRIL-MAI-JUIN' => '03',
            'AVRIL-JUILLET'     => '04',
            'AVRIL-SEPTEMBRE'   => '04',
            'AVRIL-MAI-JUIN-JUILLET' => '04',
            'MAI-AOUT'          => '05', 'MAI-JUIN-JUILLET-AOUT' => '05',
            'MAI-JUILLET'       => '05',
            'MAI-OCTOBRE'       => '05',
            'JUIN-SEPTEMBRE'    => '06',
            'JUIN-OCTOBRE'      => '06',
            'JUIN-DECEMBRE'     => '06',
            'JUILET-SEPTEMBRE'  => '07', // avec typo sur "juillet"
            'JUILLET-OCTOBRE'   => '07',
            'SEPTEMBRE-DECEMBRE'=> '09', 'SEPTEMBRE-OCTOBRE-NOVEMBRE' => '09', 'SEPTEMBRE-OCTOBRE-NOVEMBRE-DECEMBRE' => '09',
            'OCTOBRE-SEPTEMBRE' => '09', // A l'envers


            // Saisons
            //
            // printemps : du 20/21 mars au 21/22 juin
            // été : du 21/22 juin au 22/23 septembre
            // automne : du 22 septembre au 20/22 décembre
            // hiver : du 22 décembre au 20/21 mars
            'PRINTEMPS' => '03',
            'ETE'       => '06',
            'AUTOMNE'   => '09',
            'HIVER'     => '12',
            'PRINTEMPS-ETE'=> '03',
            'PRINTEMPS-AUTOMNE' => '03',
        );

        $h = strtoupper(trim($h));
        if (! isset($monthes[$h])) return "ERROR MONTH '$h'";
        return $monthes[$h];
    }
}

set_time_limit(0);
//$ajp = fopen(__DIR__ . '/AJP.TXT', 'wt');
//$url = fopen(__DIR__ . '/URL.TXT', 'wt');
$so = fopen(__DIR__ . '/SO.TXT', 'wt');
echo '<table border="1">';
echo '<tr><td>$V1</td><td>$fullnum</td><td>$V2</td><td>$date</td><td>$V3</td></tr>';
$todo=$done=$nb=0;
foreach(new PrismeReader(__DIR__ . '/24052011.TXT') as $nb => $record)
{
//    echo "--------- Enreg n° $nb ---------<br />";
//    foreach($record as $key=>$value)
//        if ($value!== '') fputs($ajp, "$key\n$value\n");
//    fputs($ajp, "//\n");


//    if (isset($record['OLDURL']))
//        fprintf($url, "OLDURL:%s\nURL...:%s\nDCSITE:%s\n\n", $record['OLDURL'], $record['URL'], $record['DCSITE']);

    if (isset($record['OLDSO']))
    {
        fprintf($so, "OLDSO:%s\nVOL..:%s\nSO...:%s\nPER..:%s\nDP...:%s\n\n", $record['OLDSO'], $record['VOL'], $record['SO'], $record['PER'], $record['DP']);
        ++$done;
    }else ++$todo;
}
echo "</table>";
echo "<br />$nb records. done=$done, todo=$todo<br />";
//fclose($ajp);
//fclose($url);
fclose($so);