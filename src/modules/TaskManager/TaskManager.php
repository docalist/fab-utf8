<?php
/**
 * @package     fab
 * @subpackage  TaskManager
 * @author      Daniel M�nard <Daniel.Menard@bdsp.tm.fr>
 * @version     SVN: $Id: TaskManager.php 1202 2010-09-10 10:10:37Z daniel.menard.bdsp $
 */

/**
 * Le gestionnaire de t�ches de fab.
 *
 * Normalement, une requ�te ex�cut�e sur le serveur web ne dispose que de
 * ressources (tr�s) limit�es pour s'ex�cuter (par exemple : temps d'ex�cution
 * maximal de 30 secondes, m�moire maximale autoris�e de 8 Mo, etc.)
 *
 * Un serveur http comme {@link http://httpd.apache.org Apache} mettra fin au
 * processus correspondant � la requ�te d�s qu'il d�tectera que les limites ont
 * �t� atteintes. Il est �galement susceptible d'arr�ter le processus s'il
 * d�tecte que le navigateur qui en est � l'origine a cess� d'attendre la
 * r�ponse (il a chang� de page par exemple).
 *
 * Dans ces conditions, il est impossible d'ex�cuter des op�rations "lourdes"
 * telles que import/export de fichiers de notices, sauvegarde de la base,
 * r�indexation compl�te, modifications et suppressions en s�rie, etc.
 *
 * Non seulement, l'op�ration ne pourra pas s'ex�cuter jusqu'au bout, mais en
 * plus le syst�me sera tr�s probablement laiss� dans un �tat instable si le
 * processus est stopp� en plein milieu d'ex�cution (certaines notices ont �t�
 * import�es mais pas toutes, la sauvegarde ne contient qu'une partie de la base
 * et est donc inutilisable, les modifications en s�rie n'ont �t� apport�es qu'�
 * certaines notices sans qu'on puisse d�teminer facilement lesquelles, etc.)
 *
 * Habituellement, la r�ponse � ce probl�me consiste � augmenter les limites
 * autoris�es � l'aide de fonction php telles que
 * {@link http://php.net/set_time_limit set_time_limit},
 * {@link http://php.net/ignore_user_abort ignore_user_abort()} ou
 * {@link http://php.net/apache_reset_timeout apache_reset_timeout} mais la
 * solution obtenue est en g�n�ral peu satisfaisante (ces fonctions sont parfois
 * d�sactiv�es, les scripts ex�cut�s doivent �tre modifi�s de fa�on ad hoc, cela
 * peut repr�senter un risque de s�curit�, etc.)
 *
 * Un autre probl�me courant est li� au fait que certaines ressources
 * n�cessaires � la bonne ex�cution du script peuvent, temporairement, ne pas
 * �tre disponibles.
 *
 * On peut imaginer par exemple un script qui envoie par e-mail � l'utilisateur
 * ses codes d'acc�s une fois que celui-ci s'est inscrit sur le site. Si au
 * moment d'envoyer l'e-mail le serveur de messagerie n'est pas disponible,
 * l'e-mail ne sera jamais envoy� et l'utilisateur ne recevra jamais ses codes
 * d'acc�s.
 *
 * Le TaskManager de fab est une r�ponse � ces probl�mes : il fournit un
 * environnement d'ex�cution sp�cifique (ind�pendant du serveur http utilis�)
 * dans lequel des t�ches telles que celles d�crites ci-dessus vont pouvoir �tre
 * ex�cut�es.
 *
 * Dans la pratique, au lieu d'ex�cuter un traitement long et/ou important
 * dans le script qui traite la requ�te adress�e par le navigateur, on va cr�er
 * une t�che qui sera ensuite ex�cut�e par le gestionnaire de t�ches.
 *
 * Le gestionnaire de t�ches offre �galement d'autres services :
 * - possibilit� de programmer une t�che pour qu'elle s'ex�cute d�s que possible
 *   ou � une date ult�rieure.
 * - possibilit� de programmer une t�che r�currente qui s'ex�cutera
 *   p�riodiquement � une heure et selon un intervalle d�finis.
 * - l'administrateur du site dispose d'une interface web lui permettant de
 *   consulter l'historique des t�ches ex�cut�es, la liste des t�ches qui n'ont
 *   pas pu �tre lanc�es. L'interface lui permet �galement d'annuler des t�ches
 *   en attente ou de relancer une t�che qui ne s'est pas ex�cut�e correctement.
 * - La sortie g�n�r�e par l'ex�cution d'une t�che est conserv�e, ce qui permet
 *   � l'administrateur de contr�ler la bonne ex�cution de celle-ci. De mani�re
 *   g�n�rale, l'ensemble des t�ches ex�cut�es constitue un historique complet
 *   de toutes les t�ches d'administration li�es au site.
 *
 * Techniquement, la classe TaskManager a plusieurs facettes :
 * - Un {@link actionDaemon() d�mon} (sous Windows on parlerait d'un service)
 *   qui tourne en permanence et se charge d'ex�cuter les t�ches au bon moment.
 * - Un serveur de sockets qui �coute sur un port TCP et permet la communication
 *   inter-process entre le gestionnaire de t�ches et les scripts de
 *   l'application
 * - Une API utilisable dans les applications pour manipuler les t�ches et le
 *   d�mon : {@link isRunning()}, {@link status()}, {@link start()},
 *   {@link stop()}, {@link daemonUpdate()}.
 * - Un module standard de fab qui h�rite de DatabaseModule : l'ensemble des
 *   t�ches est g�r� sous forme de base de donn�es. Toutes les actions classiques
 *   de DatabaseModule (search, show, load, delete...) sont disponibles.
 * - Des actions sp�cifiques telles que {@link actionStart()},
 *   {@link actionStop()}, {@link actionRestart()}, {@link actionTaskStatus()}
 *   permettant de contr�ler l'ex�cution du TaskManager et des t�ches.
 *
 * @package     fab
 * @subpackage  TaskManager
 */
class TaskManager extends DatabaseModule
{
    /**
     * Identifiant de la t�che en cours d'ex�cution.
     *
     * N'est d�finit que lorsqu'on {@link actionRunTask()} est appell�e.
     * Permet � {@link progress()} et au {@link taskOutputHandler()
     * captureHandler} de savoir � quelle t�che ils ont affaire.
     *
     * @var int
     */
    private static $id=null;

    /**
     * Path du fichier utilis� pour capturer la sortie g�n�r�e lors
     * de l'ex�cution d'une t�che.
     *
     * N'existe que lorsqu'on est pass� dans {@link actionRunTask()}.
     * Permet au captureHandler de savoir o� �crire les donn�es.
     *
     * @var string
     */
    private static $outputFile=null;


    /**
     * Retourne le path complet de la base de donn�es utilis�e par le
     * gestionnaire de t�ches.
     *
     * Raison : a base utilis�e doit �tre unique pour un serveur donn�e et donc
     * n'est pas stock�e dans le r�pertoire data/db d'une application mais
     * dans le r�pertoire data/db de fab. Du coup, il ne faut pas qu'on passe
     * par le syst�me d'alias (db.config) habituel.
     *
     * Remarque : le path obtenu ne contient PAS un slash ou un antislash
     * final.
     *
     * @return string
     */
    public static function getDatabasePath()
    {
        return Runtime::$fabRoot
            . 'data'    . DIRECTORY_SEPARATOR
            . 'db'      . DIRECTORY_SEPARATOR
            . 'tasks';
    }

    /**
     * Retourne le path complet du r�pertoire dans lequel sont stock�s les
     * fichiers de sortie g�n�r�s par les t�ches.
     *
     * Remarque : le path obtenu contient toujours un slash ou un antislash
     * final.
     *
     * @return string
     */
    public static function getOutputDirectory()
    {
        return self::getDatabasePath()
            . DIRECTORY_SEPARATOR
            . 'files' . DIRECTORY_SEPARATOR;
    }

    /**
     * M�thode appell�e avant l'ex�cution d'une action du TaskManager.
     *
     * On fait deux choses :
     * - on d�finit la base de donn�es utilis�e (la base 'Tasks') en modifiant
     *   dynamiquement la configuration
     * - pour l'action {@link actionSearch()}, on d�finit les filtres �
     *   appliquer aux �quations de recherche en fonction du param�tre 'done'
     *   �ventuellement pass� en param�tre.
     */
    public function preExecute()
    {
        // Indique � toutes les actions de ce module o� et comment ouvrir la base tasks
        $database=self::getDatabasePath();
        Config::set('database', $database);
        Config::set("db.$database.type", 'xapian');

        // Si la base tasks n'existe pas encore, on essaie de la cr�er de fa�on transparente
        if (!file_exists($database))
        {
            $path=Runtime::$fabRoot
                . 'data'              . DIRECTORY_SEPARATOR
                . 'schemas' . DIRECTORY_SEPARATOR
                . 'tasks.xml';

            try
            {
                if (! file_exists($path))
                    throw new Exception("Sch�ma tasks.xml non trouv�e");

                $dbs=new DatabaseSchema(file_get_contents($path));
                Database::create($database, $dbs, 'xapian');
                if (!@mkdir($path=self::getOutputDirectory(), 0777))
                    throw new Exception("Impossible de cr�er le r�pertoire $path");
            }
            catch (Exception $e)
            {
                throw new Exception("Erreur de configuration : la base tasks n'existe pas et il n'est pas possible de la cr�er maintenant (".$e->getMessage().")");
            }
        }

        // Pour l'action search ajoute un filtre si l'option "masquer l'historique" est active
        if ($this->method==='actionSearch')
        {
            if (!$this->request->bool('done')->defaults(false)->ok())
                $this->request->add('_filter', 'last='.strftime('%Y%m%d*').' OR (NOT status:done)');
        }
    }

	// ================================================================
	// LE GESTIONNAIRE DE TACHES PROPREMENT DIT
	// ================================================================

    /**
     * Fonction de d�bogage permettant de suivre l'ex�cution du d�mon lorsque
     * celui-ci est lanc� directement en ligne de commande.
     *
     * @param string $message
     * @param string $client
     */
	private static function out($message, $client = null)
	{
		$h= date('d/m/y H:i:s');
		if ($client)
			$h.= ' (' . $client . ')';
		$h.= ' - ' . Utils::convertString($message,'CP1252 to CP850') . "\n";

		echo $h;
		flush();
		while (ob_get_level())
			ob_end_flush();

//        $path=self::getOutputDirectory() . 'daemon.html';
//		file_put_contents($path, $h, FILE_APPEND);
	}

    /**
     * Retourne la prochaine t�che � ex�cuter
     *
     * La fonction lance une recherche dans la base en triant les r�ponses
     * par date de prochaine ex�cution.
     *
     * Remarque : utilis�e uniquement par {@link actionDaemon()}.
     *
     * @return null|Task retourne null s'il n'y a aucune t�che en attente et
     * un objet Task correspondant � la premi�re r�ponse obtenue sinon.
     */
	private function getNextTask()
	{
        $this->openDatabase(true);
        $equation=sprintf('Status:%s', Task::Waiting);

        if ($this->select($equation, 1, 1, 'next+'))
            $task=new Task($this->selection);
        else
            $task=null;

        unset($this->selection);
        self::out("Recherche de la prochaine t�che � ex�cuter. Equation=$equation, T�che=".(is_null($task) ? 'null' : $task->getId(false)));
        return $task;
	}

	/**
	 * Le d�mon du taskmanager est un process destin� � �tre ex�cut� en ligne
	 * de commande et qui tourne ind�finiment.
	 *
	 * Son r�le est d'ex�cuter les t�ches programm�es quand leur heure est
	 * venue.
	 *
	 * C'est �galement un serveur bas� sur des sockets TCP qui r�ponds aux
	 * requ�tes adress�es par les clients.
	 */
	public function actionDaemon()
	{
		// Tableau stockant l'�tat de progression des t�ches
	    $progress=array();

	    echo "\n\n\n";
	    self::out('D�marrage du gestionnaire de t�ches');

		// D�termine les options du gestionnaire de t�ches
        $IP=Config::get('taskmanager.localIP');
	    $port = Config::get('taskmanager.port');
        $address= 'tcp://' . $IP . ':' . $port;
        $startTime = strftime('%d/%m/%Y %H:%M:%S');

		// D�marre le serveur
		$errno = 0; // �vite warning 'var not initialized'
		$errstr = '';
		$socket = stream_socket_server($address, $errno, $errstr);
		if (!$socket)
			die("Impossible de d�marrer le gestionnaire de t�ches : $errstr ($errno)\n");

		$client = '';

        // Charge la liste des t�ches � ex�cuter
        //$tasks=new TaskList();

        // todo : rep�rer les t�ches qui n'ont pas �t� ex�cut�es � l'heure pr�vue
        $task=$this->getNextTask();
		while (true)
        {
            // d�but calcul du timeout

            // Calcule le temps � attendre avant l'ex�cution de la prochaine t�che (timeout)
            if ( is_null($task) )
            {
                $timeout = 24 * 60 * 60; // aucune t�che en attente : time out de 24h
                $expired=false;
            }
            else
            {
                $next=$task->getNext();

                if ($next===0)
                    $diff=time() - $task->getCreation();
                else
                    $diff=time() - $next;

                $expired=($diff>60); // on tol�re une minute de marge

                // Si l'heure d'ex�cution pr�vue est d�pass�e, passe la t�che en statut 'Expired'
                if ($expired)
                {
                    // ie : soit next est d�pass�, soit la t�che �tait
                    // programm�e "d�s que possible", mais il s'est pass�
                    // beaucoup de temps depuis sa cr�ation
                    if ($next===0)
                        $h='ex�cution=asap, cr�ation='.strftime('%H:%M:%S',$task->getCreation()). ', now='.strftime('%H:%M:%S'). ', diff='.$diff.', > 60';
                    else
                        $h='ex�cution pr�vue � '.strftime('%H:%M:%S',$next).', now='.strftime('%H:%M:%S').', diff='.$diff.', > 60';
                    self::out('T�che '.$task->getId(false).' : date d\'ex�cution d�pass�e ('.$h.')');

                    $task->setStatus(Task::Expired)->save();
                    $task=null;
                    $timeout=0.1; // on va regarder s'il y a une requ�te puis on �tudiera la t�che suivante
                }
                else
                {
                    if ($next==0) // ex�cuter d�s que possible
                    {
                        $timeout=0.1;
                    }
                    else
                    {
                        $timeout=(float)$task->getNext()-time();
                        if ($timeout<0)
                        {
                            self::out('Erreur interne : timeout n�gatif');
                            $timeout=0;
                        }
                    }
                }
            }

            // fin calcul du timeout

			// actuellement (php 5.1.4) si on met un timeout de plus de 4294, on a un overflow
			// voir bug http://bugs.php.net/bug.php?id=38096
//			if ($timeout > 4294) $timeout = 4294; // = 1 heure, 11 minutes et 34 secondes

			// Attend que quelqu'un se connecte ou que le timeout expire
			self::out('en attente de connexion, timeout='.$timeout.(is_null($task) ? ', aucune t�che en attente' : (', prochaine t�che : '.$task->getId(false))));
			if ($conn = @ stream_socket_accept($socket, $timeout, $client))
			{
				// Extrait la requ�te
				$message = fread($conn, 1024);
				$cmd = strtok($message, ' ');
				$param = trim(substr($message, strlen($cmd)));

                self::out('Request : '.$cmd);

				// Traite la commande
				$result = 'OK';
				switch ($cmd)
				{
					case 'running?':
						$result = 'yes';
						break;

					case 'status':
						$result=sprintf
						(
                            'D�marr� depuis le %s (PID : %d, IP : %s, port tcp : %d, serveur : %s, memoire utilis�e : %s)',
                            $startTime, getmypid(), $IP, $port, php_uname('n'), Utils::formatSize(memory_get_usage(true))
                        );
						break;

                    case 'update':
                        $task=$this->getNextTask();
                        break;

                    case 'echo':
                        self::out($param);
                        break;

                    case 'setprogress':
                        sscanf($param, '%d %d %d', $id, $step, $max);

                        // setprogress id : supprime l'entr�e progress[id]
                        if ($step===0 && $max===0)
                        {
                            unset($progress[$id]);
                        }

                        // setprogress id step : modifie step, garde le max existant
                        elseif($max===0)
                        {
                            if (isset($progress[$id]))
                                $progress[$id][0]=$step;
                            else
                                $progress[$id]=array($step,999999); // erreur max jamais indiqu�
                        }

                        // stocke tout
                        else
                        {
                            $progress[$id]=array($step,$max);
                        }
                        break;

                    case 'getprogress':
                        $id=(int)$param;
                        if (isset($progress[$id]))
                            $result=$progress[$id][0] . ' ' . $progress[$id][1];
                        else
                            $result='';
                        break;

                    case 'quit':
						break;

					default :
						$result = 'error : bad command';
				}

				// Envoie la r�ponse au client
				fputs($conn, $result);
				fclose($conn);

				// Si on a re�u une commande d'arr�t, termin�, sinon on recommence
				if ($cmd == 'quit') break;

				if ($expired) $task=$this->getNextTask();
			}

			// On est sorti en time out, ex�cute la t�che en attente s'il y en a une
			else
			{
                self::out('Dans le else');

			    if (! is_null($task))
                {
                    self::out('Dans le if');
                    // Modifie la date de derni�re ex�cution et le statut de la t�che
                    $task->setLast(time());
                    $task->setStatus(Task::Starting);
                    $task->save();

                    // Ferme le socket serveur pour emp�cher le process fils d'en h�riter
                    fclose($socket);

                    // Lance la t�che
                    self::out('Lancement de la t�che '. $task->getId(false));
                    self::runBackgroundModule('/TaskManager/RunTask?id=' . $task->getId(false), $task->getApplicationRoot(), $task->getUrl());

                    // R�ouvre le socket serveur
                    $socket = stream_socket_server($address, $errno, $errstr);
                    if (!$socket)
                        die("Impossible de recr�er le socket serveur apr�s l'ex�cution de la t�che : $errstr ($errno)\n");

                    $task=null; // indique que la t�che a �t� ex�cut�e, il faut chercher la suivante
                }
                self::out('appel de getNextTask()');
                $task=$this->getNextTask();
			}
		}

		// Arr�te le serveur
		self::out('Arr�t du gestionnaire de t�ches');
		fclose($socket);
		self::out('Le gestionnaire de t�ches est arr�t�');
		Runtime::shutdown();
	}

    /**
     * Lance l'ex�cution d'une t�che
     *
     * L'action RunTask est appell�e par le {@link actionDaemon() d�mon} pour
     * lancer une t�che. Elle ne doit �tre appell�e qu'en ligne de commande.
     *
     * La fonction commence par charger la t�che dont l'id a �t� indiqu� en
     * param�tre.
     *
     * Elle installe ensuite un {@link taskOutputHandler() gestionnaire
     * ob_start()} qui va se charger d'�crire dans un fichier tout ce que la
     * t�che � ex�cuter �crira sur la sortie standard.
     *
     * La t�che est alors pass�e en statut {@link Task::Running} et le nom
     * du fichier de sortie g�n�r� est stock� dans l'attribut
     * {@link Task::getOutput() OutputFile} de la t�che.
     *
     * Enfin elle lance l'ex�cution de la t�che en demandant au module Routing
     * {@link Routing::run() d'ex�cuter} la requ�te associ�e � la t�che �
     * ex�cuter.
     *
     * A la fin de l'ex�cution, la t�che est automatiquement pass�e en statut
     * {@link Task::Done}. Si une erreur (i.e. une exception) survient durant
     * l'ex�cution de la t�che, celle-ci est captur�e et la t�che est alors
     * pass�e en statut {@link Task::Error}.
     *
     * Remarques :
     * - Le fichier de sortie est stock� dans le sous-r�pertoire 'files' de la
     *   base de donn�es.
     * - Pour une t�che r�currente, le fichier de sortie g�n�r� sera �cras� �
     *   chaque nouvelle ex�cution de la t�che. Il serait assez simple d'adapter
     *   le code pour conserver le r�sultat des n derni�res ex�cutions.
     * - Le gestionnaire ob_start est param�tr� de telle fa�on que la sortie
     *   g�n�r�e par la t�che soit "flush�e" d�s que possible.
     *
     * @param int $id l'identifiant unique de la t�che � ex�cuter.
     */
    public function actionRunTask($id)
    {
        // R�cup�re l'ID de la t�che � ex�cuter
        $id=$this->request->int('id')->required()->min(1)->ok();

        // Charge la t�che indiqu�e
        $task=new Task($id);

        // D�termine le path du fichier de sortie de la t�che
        $path=self::getOutputDirectory().$task->getId(false) . '.html';

        // On se charge nous m�me d'ouvrir (et de fermer, cf plus bas) le fichier
        // Car si on laisse ouputHandler le faire, on n'a aucun moyen de r�cup�rer les pb �ventuels (fichier non trouv�, etc.)
        if (false === self::$outputFile=@fopen($path, 'w'))
        {
            $task->setStatus(Task::Error)->setLabel($task->getLabel()." -- Erreur : impossible d'ouvrir le fichier $path en �criture")->save();
            // todo: on n'a aucun champ dans la base pour stocker l'erreur, pour le moment on met dans le label
            // en m�me temps, ce type d'erreur ne se produira pas si tout est bien configur�
            return;
        }

        // M�morise l'ID et le OutputFile de la t�che (utilis� par progress et taskOutputHandler)
        self::$id=$task->getId(false);

        // A partir de maintenant, redirige tous les echo vers le fichier OutputFile
        ob_start(array('TaskManager', 'taskOutputHandler'), 2);//, 4096, false);
        // ob_implicit_flush(true); // aucun effet en CLI. Le 2 ci-dessus est un workaround
        // cf : http://fr2.php.net/manual/en/function.ob-implicit-flush.php#60973

        // Indique que l'ex�cution a d�marr�
        $start=time();
        $task->setStatus(Task::Running)->setLast($start)->setOutput(self::$outputFile)->save();
        TaskManager::request('echo D�but de la t�che #'.self::$id);

        echo sprintf
        (
            '<div class="taskinfo">T�che #%s : %s<br />Date d\'ex�cution : %s<br />Requ�te ex�cut�e : %s<br />PID : %d</div>',
            self::$id, $task->getLabel(), strftime('%d/%m/%Y %H:%M:%S'), $this->request, getmypid()
        );

        // Construit la requ�te � ex�cuter
        $request=$task->getRequest();

        Runtime::$request=$request;

        // Ex�cute la t�che
        try
        {
            Module::run($request);
        }

        // Une erreur s'est produite
        catch (Exception $e)
        {
            // ferme la barre de progression �ventuelle
            self::progress();

            $task->setStatus(Task::Error)->save();
            //ExceptionManager::handleException($e, false);
            throw $e;
            ob_end_flush();
            fclose(self::$outputFile);
        	return;
        }

        // Ferme la barre de progression �ventuelle
        self::progress();

        // Indique que la t�che s'est ex�cut�e correctement
        $task->setStatus($task->getRepeat() ? Task::Waiting : Task::Done)->save();

        echo sprintf
        (
            '<div class="taskinfo">T�che #%s : termin�e<br />Fin d\'ex�cution : %s<br />Dur�e d\'ex�cution : %s<br /></div>',
            self::$id, strftime('%d/%m/%Y %H:%M:%S'), Utils::friendlyElapsedTime(time()-$start)
        );

        ob_end_flush();
        fclose(self::$outputFile);
    }

    /**
     * Gestionnaire ob_start utilis� pour capturer la sortie des t�ches
     *
     * Ces gestionnaire est install� et d�sinstall� par {@link actionRunTask()}.
     *
     * Cette fonction est automatiquement appell�e par php lorsque la t�che
     * en cours d'ex�cution �crit quelque chose sur la sortie standard, elle ne
     * doit pas �tre appell�e directement.
     *
     * Consultez la {@link http://php.net/ob_start documentation de ob_start()}
     * pour plus d'informations.
     *
     * Remarques :
     * - dans l'id�al, cette m�thode devrait �tre d�clar�e 'private'
     *   mais php exige que les callback utilis�s avec ob_start soient 'public'.
     * - La classe {@link Utils} contient �galement des m�thodes permettant
     *   de capturer la sortie standard dans un fichier. Le code a �t� r�p�t�
     *   car dans la version actuelle, la classe Utils n'autorise qu'un niveau
     *   de capture. Si une t�che a elle-m�me besoin de capturer quelque chose
     *   (par exemple pour g�n�rer un fichier d'export), on aurait �t� bloqu�.
     *
     * @param string $buffer les donn�es � �crire
     *
     * @param int $phase un champ de bits constitu� des constantes
     * PHP_OUTPUT_HANDLER_START, PHP_OUTPUT_HANDLER_CONT et PHP_OUTPUT_HANDLER_END
     * de php.
     *
     * @return bool la fonction retourne false en cas d'erreur (si le fichier
     * de sortie ne peut pas �tre ouvert en �criture.
     */
    public static function taskOutputHandler($buffer, $phase)
    {
        fwrite(self::$outputFile, $buffer);
        return ; // ne pas mettre return true, sinon php affiche '111111...'
    }

    /**
     * Lance l'ex�cution en t�che de fond (en arri�re-plan) d'une action.
     *
     * La fonction cr�e un nouveau processus php qui va ex�cuter fab en mode
     * CLI puis chargera le module indiqu� et ex�cutera l'action demand�e.
     *
     * Pour cela, elle d�termine la ligne de commande � utiliser puis utilise
     * les fonctions du syst�me d'exploitation pour ex�cuter la commande obtenue
     * en t�che de fond :
     *
     * - sous linux, le symbole <code>&</code> est simplement ajout� � la
     *   commande � ex�cuter ;
     *
     * - sous windows, une instance du composant ActiveX
     *   <code>WScript.Shell</code> de Windows est cr��e et sa m�thode
     *   <code>Run</code> est appell�e en passant la valeur <code>false</code>
     *   pour le param�tre <code>bWaitOnReturn</code>. Consultez la
     *   {@link http://msdn.microsoft.com/en-us/library/d5fk67ky(VS.85).aspx
     *   documentation de Microsoft} pour plus d'informations sur le composant
     *   WScript de Windows.
     *
     * Pour d�terminer la ligne de commande � utiliser, la fonction se base sur
     * les informations pr�sentes dans la section <code><taskmanager></code> du
     * fichier de configuration <code>general.config</code> et sur les arguments
     * <code>$root</code> et <code>$home</code> pass�s en param�tre :
     *
     * - elle r�cup�re dans la cl� <code><php></code> le path exact de
     *   l'ex�cutable php � utiliser. Une erreur est g�n�r�e si cette cl� n'est
     *   pas renseign�e ou si elle d�signe un fichier inexistant ou autre chose
     *   qu'un ex�cutable. Si le path obtenu contient des espaces, des
     *   guillemets sont ajout�s au d�but et � la fin.
     *
     * - elle r�cup�re dans la cl� <code><phpargs></code> les options
     *   �ventuelles � passer � l'ex�cutable php.
     *
     * - elle ajoute le path exact du fichier php � ex�cuter en l'encadrant si
     *   n�cessaire de guillemets. En g�n�ral, il s'agit du path exact du front
     *   controler de l'application (typiquement, c'est le path du fichier
     *   <code>index.php</code> qui figure dans le r�pertoire web de
     *   l'application) mais un fichier diff�rent peut �tre utilis� en
     *   passant en param�tres des valeurs pour <code>$root</code> et
     *   <code>$home</code>.
     *
     * - elle ajoute ensuite les param�tres du script � savoir le module et
     *   l'action � ex�cuter(<code>$fabUrl</code>) et la valeur indiqu�e pour le
     *   param�tre <code>$home</code>.
     *
     * Exemple :
     * Si l'application est install�e dans le r�pertoire <code>/site</code> et
     * que la configuration contient les valeurs suivantes :
     * <code>
     *     <taskmanager>
     *         <php>/usr/bin/php</php>
     *         <phpargs>-n -f</phpargs>
     *     </taskmanager>
     * </code>
     * la ligne de commande qui sera ex�cut�e sera de la forme :
     * <code>
     * /usr/bin/php -n -f /site/web/index.php /module/action?params &
     *    <php>  <phpargs>  front controler           $fabUrl
     * </code>
     *
     * Remarque :
     * Sous linux l'ex�cutable php s'appelle simplement <code>php</code>. Sous
     * windows, il faut utiliser <code>php.exe</code> ou, ce qui est pr�f�rable,
     * l'ex�cutable sp�cifique <code>php-win.exe</code> afin d'�viter la
     * cr�ation d'une console (consulter la
     * {@link http://php.net/manual/features.commandline.php documentation sur
     * l'utilisation de php en ligne de commande} pour plus d'informations).
     *
     * @param string $fabUrl la fab url (/module/action?params) � ex�cuter
     * @param string $root la racine de l'application � passer en param�tre �
     * {@link Runtime::setup()}.
     */
    private static function runBackgroundModule($fabUrl, $root='', $home='')
    {
        // D�termine le path de l'ex�cutable php-cli
        if (!$cmd = Config::get('taskmanager.php', ''))
            throw new Exception('Le path de l\'ex�cutable php ne figure pas dans la config');

        // V�rifie que le programme php obtenu existe et est ex�cutable
        if (!is_executable($cmd))
            throw new Exception("Impossible de trouver $cmd");

        // Si le path contient des espaces, ajoute des guillemets
        $cmd=escapeshellarg($cmd); // escapeshellcmd ne fait pas ce qu'on veut. bizarre

        // D�termine les options �ventuelles � passer � php
        $args = Config::get('taskmanager.phpargs');
        if ($args)
            $cmd .= ' ' . $args;

        // Ajoute le path du fichier php � ex�cuter
        if ($home)
            $phpFile = $root . 'web' . DIRECTORY_SEPARATOR . basename($home);
        else
            $phpFile = Runtime::$webRoot . Runtime::$fcName;

        $cmd .= ' ' . escapeshellarg($phpFile);

        // Argument 1 : module/action � ex�cuter
        $cmd .= ' ' . escapeshellarg($fabUrl);

        // Argument 2 : url de la page d'accueil de l'application (si indiqu�)
        if ($home)
            $cmd .= ' ' . escapeshellarg($home);

        // Sous windows, on utilise wscript.shell pour lancer le process en t�che de fond
        if (substr(PHP_OS, 0, 3) == 'WIN')
        {
            $WshShell = new COM("WScript.Shell");
            $oExec = $WshShell->Run($cmd, 0, false);
        }

        // Sinon, on consid�re qu'on est sous *nix et utilise le & final
        else
        {
            // Pour que exec puisse lancer la t�che de fond, il faut absolument
            // que les sorties du process soient redirig�es, faute de quoi, la
            // t�che ne sera pas lanc�e en t�che de fond.

            // Cf Note dans la documentation de php :
            // "If a program is started with this function, in order for it to
            // continue running in the background, the output of the program
            // must be redirected to a file or another output stream. Failing
            // to do so will cause PHP to hang until the execution of the
            // program ends.

            // Merci � Jean-Ren� Rouet du CCIN2P3 d'avoir trouv� la solution et
            // la syntaxe exacte � utiliser.

            // DM, 30/01/2009

        	$cmd .= ' > /dev/null 2>&1 &';

        	// Explications :
        	// "> /dev/null" = redirige stdout vers /dev/null
        	// "2>&1" = redirige la sortie standard n�2 (stderr) au m�me endroit que stdout
        	// "&" = lance le tout en t�che de fond.

            exec($cmd);
        }
    }


    // ================================================================
    // ACTIONS DU MODULE GESTIONNAIRE DE TACHES
    // ================================================================

    /**
     * Affiche le statut d'une t�che, le r�sultat de sa derni�re ex�cution, la
     * progression de l'�tape en cours.
     *
     * @param int $id l'identifiant de la t�che � afficher.
     * @param int $start un offset indiquant la partie de la sortie g�n�r�e
     * par la t�che � r�cup�rer.
     * @return Response
     */
    public function actionTaskStatus($id, $start=0)
    {
        // R�cup�re l'ID de la t�che � ex�cuter
        self::$id=$this->request->int('id')->required()->min(1)->ok();

        // Charge la t�che indiqu�e
        $task=new Task($id);

        // Cr�e la r�ponse. Si on est en mode ajax, supprime le layout
        $response = Response::create('html');
        if (Utils::isAjax())
            Config::set('layout','none');

        // Ajoute le contenu du fichier de sortie de la t�che dans la r�ponse
        $outputFile=self::getOutputDirectory().$task->getId(false) . '.html';
        if (file_exists($outputFile))
        {
            $data = file_get_contents($outputFile, false, null, $start);
            $response->appendContent($data);
            $start += strlen($data);
        }

        switch ($status=$task->getStatus())
        {
            case Task::Waiting :
            case Task::Starting :
            case Task::Running :
                $progress=self::request('getprogress '.$id);
                if ($progress==='')
                    $step=$max=0;
                else
                    sscanf($progress, '%d %d', $step, $max);

                $response->appendContent
                (
                    sprintf
                    (
                        '<span id="updater" url="%s" step="%d" max="%d"></span>',
                        Routing::linkFor($this->request->copy()->set('start', $start)->getUrl()),
                        $step, $max
                    )
                );
                break;

            case Task::Done:
            case Task::Error:
                break;

            case Task::Disabled:
            case Task::Expired:
                $response->appendContent('<p>La t�che est en statut "' . $status . '".</p>');
                break;
        }
        return $response;
    }

    /**
     * Action permettant � l'utilisateur de d�marrer le d�mon.
     *
     * La fonction {@link start()} est appell�e puis l'utilisateur est redirig�
     * vers la page d'accueil du gestionnaire de t�ches.
     *
     * @return Response
     */
    public function actionStart()
    {
        self::start();
        return new RedirectResponse('index');
    }

    /**
     * Action permettant � l'utilisateur d'arr�ter le d�mon.
     *
     * La fonction {@link stop()} est appell�e puis l'utilisateur est redirig�
     * vers la page d'accueil du gestionnaire de t�ches.
     *
     * @return Response
     */
    public function actionStop()
    {
        try
        {
            self::stop();
        }
        catch (Exception $e)
        {
            return Response::create('html')->setContent('Erreur : ' . $e->getMessage());
        }
        return new RedirectResponse('index');
    }

    /**
     * Action permettant � l'utilisateur de red�marrer le d�mon.
     *
     * La fonction {@link restart()} est appell�e puis l'utilisateur est
     * redirig� vers la page d'accueil du gestionnaire de t�ches.
     *
     * @return Response
     */
    public function actionRestart()
    {
        self::restart();
        return new RedirectResponse('index');
    }

	// ================================================================
	// API DU GESTIONNAIRE DE TACHES
	// ================================================================

	/**
	 * Indique si le gestionnaire de t�ches est en cours d'ex�cution ou non.
	 *
	 * @return bool
	 */
	public static function isRunning()
	{
		return self::request('running?') == 'yes';
	}

	/**
	 * D�marre le gestionnaire de t�ches.
	 *
	 * G�n�re une exception en cas d'erreur (gestionnaire d�j� d�marr�,
	 * impossible de lancer le process, etc.)
	 *
	 * Remarque : une pause de une seconde est appliqu�e apr�s avoir lanc� le
	 * gestionnaire de t�ches pour laisser le temps au d�mon de s'initialiser
	 * et de d�marrer.
	 *
	 * @return bool true si le serveur a pu �tre d�marr�, faux sinon.
	 */
	public static function start()
	{
		if (self::isRunning())
			throw new Exception('Le gestionnaire de t�ches est d�j� lanc�');

        if (! Config::get('taskmanager.webcontrol'))
            throw new Exception('Acc�s refus�, contactez votre administrateur syst�me.');

        self::runBackgroundModule('/TaskManager/Daemon');

        sleep(1); // on lui laisse un peu de temps pour d�marrer

        return self::isRunning();
	}

	/**
	 * Arr�te le gestionnaire de t�ches.
	 *
	 * G�n�re une exception en cas d'erreur (gestionnaire non d�marr�,
	 * impossible de lancer le process, etc.)
	 *
	 * @return bool true si le serveur a pu �tre arr�t�, faux sinon.
	 */
	public static function stop()
	{
		if (!self::isRunning())
			throw new Exception('Le gestionnaire de t�ches n\'est pas lanc�');

        if (! Config::get('taskmanager.webcontrol'))
            throw new Exception('Acc�s refus�, contactez votre administrateur syst�me.');

        return self::request('quit');
	}

	/**
	 * Red�marre le gestionnaire de t�ches. Equivalent � un stop suivi d'un
	 * start.
	 *
	 * @return bool true si le serveur a pu �tre red�marr�, faux sinon.
	 */
	public static function restart()
	{
        if (! Config::get('taskmanager.webcontrol'))
            throw new Exception('Acc�s refus�, contactez votre administrateur syst�me.');

        if (self::isRunning())
			if (!self::stop())
				return false;
		return self::start();
	}

	/**
	 * Indique le statut du gestionnaire de t�ches (non d�marr�, lanc�
	 * depuis telle date...)
	 *
	 * @return bool string
	 */
	public static function status()
	{
		if (!self::isRunning())
			return 'Le gestionnaire de t�ches n\'est pas lanc�';
		return self::request('status');
	}


    /**
     * Envoie une requ�te demandant au d�mon de rafraichir ses donn�es.
     *
     * Cette fonction est utilis�e lorsque des modifications sont apport�es
     * � la base des t�ches (cr�ation ou suppression d'une t�che, nouvelle
     * programmation, etc.).
     *
     * Elle permet de dire au d�mon que la liste des t�ches en attente a
     * peut-�tre chang� et qu'il doit mettre � jour ses strctures de donn�es
     * internes.
     */
    public static function daemonUpdate()
    {
        self::request('update');
    }

    /**
	 * Envoie une requ�te au d�mon du gestionnaire de t�ches et retourne la
	 * r�ponse obtenue.
	 *
	 * @param string $command la commande � envoyer au d�mon.
	 * @param string $error une variable qui en sortie recevra les �ventuelles
	 * erreurs TCP obtenues lors de la requ�te.
	 * @return string la r�ponse retourn�e par le d�mon.
	 */
	private static function request($command, & $error = '')
	{
        $IP=Config::get('taskmanager.remoteIP');
        $port = Config::get('taskmanager.port');
        $address= 'tcp://' . $IP . ':' . $port;

		$timeout = (float) Config::get('taskmanager.timeout'); // en secondes, un float

		// Cr�e une connexion au serveur
        $errno = 0; // �vite warning 'var not initialized'
        $errstr = '';
		$socket = @stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

		if (!is_resource($socket))
		{
			$error = "$errstr ($errno)";
			return null;
		}

		// D�finit le timeout
		$timeoutSeconds = (int) $timeout;
		$timeoutMicroseconds = ($timeout - ((int) $timeout)) * 1000000;
		stream_set_timeout($socket, $timeoutSeconds, $timeoutMicroseconds);

		// Envoie la commande
		$ret = @ fwrite($socket, $command);
		if ($ret === false or $ret === 0)
		{
			// BUG non �lucid� : on a occasionnellement une erreur 10054 : connection reset by peer
			// lorsque cela se produit il n'est plus possible de faire quoique ce soit, m�me si
			// le serveur distant est d�marr� (test� et constat� uniquement lors d'un start)
			fclose($socket);
			return;
		}
		fflush($socket);

		// Lit la r�ponse (le timeout s'applique)
		$response = stream_get_contents($socket);

		// Ferme la connexion
		fclose($socket);

		// Retourne la r�ponse obtenue
		return $response;
	}

	// ================================================================
	// CREATION DE T�CHES
	// ================================================================

    /**
     * Permet � une t�che en cours d'ex�cution de faire �tat de sa progression.
     *
     * Le but de la fonction progress est de cr�er une barre de progression
     * qui sera affich�e dans le {@link actionTaskStatus() statut de la t�che}
     * pendant que celle-ci est en cours d'ex�cution.
     *
     * La fonction calcule un pourcentage � partir des valeurs indiqu�es (�tape
     * en cours et nombre total d'�tapes) et ce pourcentage sera utilis� pour
     * mettre � jour le "niveau de remplissage" de la barre de progression.
     *
     * Si progress a d�j� �t� appell� en indiquant le nombre total d'�tapes,
     * vous pouvez, dans l'omettre dans les appels suivants.
     *
     * Si la fonction est appell�e sans aucun param�tres, la barre de progression
     * est ferm�e. Cette �tape n'est en g�n�ral pas utile car la barre de
     * progression est de toute fa�on ferm�e automatiquement � la fin de
     * l'ex�cution de la t�che. De m�me, si vous ouvrez une nouvelle barre de
     * progression (un sp�cifiant un nouveau max, �a ferme automatiquement la
     * barre de progression pr�c�dente).
     *
     * @param int $step l'�tape en cours
     * @param int $max nombre total d'�tapes
     */
    public static function progress($step=0, $max=0)
    {
        self::request(sprintf('setprogress %d %d %d', self::$id, $step, $max));
    }
}