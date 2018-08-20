<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use AppBundle\Util\LogHandler;
use AppBundle\Util\RegistroUtil;
use AppBundle\Util\BachecaUtil;
use AppBundle\Entity\Assenza;
use AppBundle\Entity\Alunno;
use AppBundle\Entity\Entrata;
use AppBundle\Entity\Uscita;
use AppBundle\Entity\Staff;
use AppBundle\Form\Appello;
use AppBundle\Form\AppelloType;


/**
 * AssenzeController - gestione delle assenze
 */
class AssenzeController extends Controller {

  /**
   * Gestione delle assenze
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param BachecaUtil $bac Funzioni di utilità per la gestione della bacheca
   * @param int $cattedra Identificativo della cattedra
   * @param int $classe Identificativo della classe (supplenza)
   * @param string $data Data del giorno da visualizzare (AAAA-MM-GG)
   * @param string $vista Tipo di vista del registro (giorno/settimana/mese)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/quadro/{cattedra}/{classe}/{data}/{vista}", name="lezioni_assenze_quadro",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "vista": "G|S|M"},
   *    defaults={"cattedra": 0, "classe": 0, "data": "0000-00-00", "vista": "G"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function quadroAction(Request $request, EntityManagerInterface $em, SessionInterface $session,
                                RegistroUtil $reg, BachecaUtil $bac, $cattedra, $classe, $data, $vista) {
    // inizializza variabili
    $lista_festivi = null;
    $errore = null;
    $dati = null;
    $num_avvisi = 0;
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    $mesi = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    // parametri cattedra/classe
    if ($cattedra == 0 && $classe == 0) {
      // recupera parametri da sessione
      $cattedra = $session->get('/APP/DOCENTE/cattedra_lezione');
      $classe = $session->get('/APP/DOCENTE/classe_lezione');
    } else {
      // memorizza su sessione
      $session->set('/APP/DOCENTE/cattedra_lezione', $cattedra);
      $session->set('/APP/DOCENTE/classe_lezione', $classe);
    }
    // parametro data
    if ($data == '0000-00-00') {
      // data non specificata
      if ($session->get('/APP/DOCENTE/data_lezione')) {
        // recupera data da sessione
        $data_obj = \DateTime::createFromFormat('Y-m-d', $session->get('/APP/DOCENTE/data_lezione'));
      } else {
        // imposta data odierna
        $data_obj = new \DateTime();
      }
    } else {
      // imposta data indicata e la memorizza in sessione
      $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
      $session->set('/APP/DOCENTE/data_lezione', $data);
    }
    // data in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $info['data_label'] =  $formatter->format($data_obj);
    // data inizio e fine vista
    if ($vista == 'S') {
      // vista settimanale
      $data_inizio = clone $data_obj;
      $data_inizio->modify('this week');
      $data_fine = clone $data_inizio;
      $data_fine->modify('+5 days');
    } elseif ($vista == 'M') {
      // vista mensile
      $data_inizio = \DateTime::createFromFormat('Y-m-d', $data_obj->format('Y-m-01'));
      $data_fine = clone $data_inizio;
      $data_fine->modify('last day of this month');
    } else {
      // vista giornaliera
      $data_inizio = $data_obj;
      $data_fine = $data_obj;
    }
    // controllo cattedra/supplenza
    if ($cattedra > 0) {
      // lezione in propria cattedra: controlla esistenza
      $cattedra = $em->getRepository('AppBundle:Cattedra')->findOneBy(['id' => $cattedra,
        'docente' => $this->getUser(), 'attiva' => 1]);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      // informazioni necessarie
      $classe = $cattedra->getClasse();
      $info['materia'] = $cattedra->getMateria()->getNomeBreve();
      $info['religione'] = ($cattedra->getMateria()->getTipo() == 'R');
      $info['alunno'] = $cattedra->getAlunno();
    } elseif ($classe > 0) {
      // supplenza
      $classe = $em->getRepository('AppBundle:Classe')->find($classe);
      if (!$classe) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
      $materia = $em->getRepository('AppBundle:Materia')->findOneByTipo('U');
      if (!$materia) {
        // errore
        throw $this->createNotFoundException('exception.invalid_params');
      }
      // informazioni necessarie
      $cattedra = null;
      $info['materia'] = $materia->getNomeBreve();
      $info['religione'] = false;
      $info['alunno'] = null;
    }
    if ($classe) {
      // recupera festivi per calendario
      $lista_festivi = $reg->listaFestivi($classe->getSede());
      // controllo data
      $errore = $reg->controlloData($data_obj, $classe->getSede());
      if (!$errore) {
        // non festivo
        $adesso = (new \DateTime())->format('H:i');
        if ($adesso >= $session->get('/CONFIG/SCUOLA/lezioni_inizio', '00:00') &&
            $adesso <= $session->get('/CONFIG/SCUOLA/lezioni_fine', '23:59')) {
          // avvisi alla classe
          $num_avvisi = $bac->bachecaNumeroAvvisiAlunni($classe);
        }
        // recupera dati
        $dati = $reg->quadroAssenzeVista($data_inizio, $data_fine, $this->getUser(), $classe, $cattedra);
      }
    }
    // salva pagina visitata
    $route = ['name' => $request->get('_route'), 'param' => $request->get('_route_params')];
    $session->set('/APP/DOCENTE/menu_lezione', $route);
    // visualizza pagina
    return $this->render('lezioni/assenze_quadro_'.$vista.'.html.twig', array(
      'pagina_titolo' => 'page.lezioni_assenze',
      'cattedra' => $cattedra,
      'classe' => $classe,
      'data' => $data_obj->format('Y-m-d'),
      'data_inizio' => $data_inizio->format('d/m/Y'),
      'data_fine' => $data_fine->format('d/m/Y'),
      'settimana' => $settimana,
      'mesi' => $mesi,
      'errore' => $errore,
      'lista_festivi' => $lista_festivi,
      'info' => $info,
      'dati' => $dati,
      'avvisi' => $num_avvisi,
    ));
  }

  /**
   * Inserisce o rimuove un'assenza
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   * @param int $id Identificativo dell'assenza (se nullo crea nuova assenza)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/assenza/{cattedra}/{classe}/{data}/{alunno}/{id}", name="lezioni_assenze_assenza",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "alunno": "\d+", "id": "\d+"})
   * @Method("GET")
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function assenzaAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg, LogHandler $dblogger,
                                 $cattedra, $classe, $data, $alunno, $id) {
    // controlla cattedra
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $em->getRepository('AppBundle:Cattedra')->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // supplenza
      $cattedra = null;
    }
    // controlla classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla alunno
    $alunno = $em->getRepository('AppBundle:Alunno')->findOneBy(['id' => $alunno, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla assenza
    if ($id > 0) {
      // assenza esistente
      $assenza = $em->getRepository('AppBundle:Assenza')->findOneBy(['id' => $id,
        'alunno' => $alunno, 'data' => $data_obj]);
      if (!$assenza) {
        // assenza non esiste, niente da fare
        return $this->redirectToRoute('lezioni_assenze_quadro');
      }
      $em->remove($assenza);
    } else {
      // controlla se esiste assenza
      $assenza = $em->getRepository('AppBundle:Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
      if ($assenza) {
        // assenza esiste già, niente da fare
        return $this->redirectToRoute('lezioni_assenze_quadro');
      }
      // inserisce nuova assenza
      $assenza = (new Assenza())
        ->setData($data_obj)
        ->setAlunno($alunno)
        ->setDocente($this->getUser());
      $em->persist($assenza);
      // controlla esistenza ritardo
      $entrata = $em->getRepository('AppBundle:Entrata')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
      if ($entrata) {
        // rimuove ritardo
        $id_entrata = $entrata->getId();
        $em->remove($entrata);
      }
      // controlla esistenza uscita
      $uscita = $em->getRepository('AppBundle:Uscita')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
      if ($uscita) {
        // rimuove uscita
        $id_uscita = $uscita->getId();
        $em->remove($uscita);
      }
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, ($cattedra ? $cattedra->getMateria() : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // ok: memorizza dati
    $em->flush();
    // ricalcola ore assenza
    $reg->ricalcolaOreAlunno($data_obj, $alunno);
    // log azione
    if ($id) {
      // log cancella assenza
      $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Cancella assenza', __METHOD__, array(
        'Assenza' => $id,
        'Alunno' => $assenza->getAlunno()->getId(),
        'Data' => $assenza->getData()->format('Y-m-d'),
        'Giustificato' => ($assenza->getGiustificato() ? $assenza->getGiustificato()->format('Y-m-d') : null),
        'Docente' => $assenza->getDocente()->getId(),
        'DocenteGiustifica' => ($assenza->getDocenteGiustifica() ? $assenza->getDocenteGiustifica()->getId() : null)
        ));
    } else {
      // log inserisce assenza
      $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Crea assenza', __METHOD__, array(
        'Assenza' => $assenza->getId()
        ));
      if (isset($id_entrata)) {
        // log cancella ritardo
        $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Cancella entrata', __METHOD__, array(
          'Entrata' => $id_entrata,
          'Alunno' => $entrata->getAlunno()->getId(),
          'Data' => $entrata->getData()->format('Y-m-d'),
          'Ora' => $entrata->getOra()->format('H:i'),
          'Note' => $entrata->getNote(),
          'Giustificato' => ($entrata->getGiustificato() ? $entrata->getGiustificato()->format('Y-m-d') : null),
          'Docente' => $entrata->getDocente()->getId(),
          'DocenteGiustifica' => ($entrata->getDocenteGiustifica() ? $entrata->getDocenteGiustifica()->getId() : null)
          ));
      }
      if (isset($id_uscita)) {
        // log cancella uscita
        $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Cancella uscita', __METHOD__, array(
          'Uscita' => $id_uscita,
          'Alunno' => $uscita->getAlunno()->getId(),
          'Data' => $uscita->getData()->format('Y-m-d'),
          'Ora' => $uscita->getOra()->format('H:i'),
          'Note' => $uscita->getNote(),
          'Docente' => $uscita->getDocente()->getId()
          ));
       }
    }
    // redirezione
    return $this->redirectToRoute('lezioni_assenze_quadro');
  }

  /**
   * Aggiunge, modifica o elimina un ritardo
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/entrata/{cattedra}/{classe}/{data}/{alunno}", name="lezioni_assenze_entrata",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "alunno": "\d+"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function entrataAction(Request $request, EntityManagerInterface $em, SessionInterface $session, RegistroUtil $reg,
                                 LogHandler $dblogger, $cattedra, $classe, $data, $alunno) {
    // inizializza
    $label = array();
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $em->getRepository('AppBundle:Cattedra')->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // supplenza
      $cattedra = null;
    }
    // imposta redirect/template/info pagine
    $referer = $request->headers->get('referer');
    $referer = str_replace($request->getSchemeAndHttpHost().$request->getBaseUrl(), '', $referer);
    if ($this->getUser() instanceof Staff && substr($referer, 0, 16) == '/staff/autorizza') {
      // pagina dello staff
      $redirect = 'staff_autorizza';
      $template = 'ruolo_staff/entrata.html.twig';
      $titolo_pagina = 'page.staff_autorizza';
    } else {
      // default
      $redirect = 'lezioni_assenze_quadro';
      $template = 'lezioni/entrata_edit.html.twig';
      $titolo_pagina = 'page.lezioni_assenze';
    }
    // controlla classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla alunno
    $alunno = $em->getRepository('AppBundle:Alunno')->findOneBy(['id' => $alunno, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge prima/ultima ora
    $orario = $reg->orarioInData($data_obj, $classe->getSede());
    // controlla entrata
    $entrata = $em->getRepository('AppBundle:Entrata')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
    if ($entrata) {
      // edit
      $entrata_old['ora'] = $entrata->getOra();
      $entrata_old['note'] = $entrata->getNote();
      $entrata_old['valido'] = $entrata->getValido();
      $entrata_old['giustificato'] = $entrata->getGiustificato();
      $entrata_old['docente'] = $entrata->getDocente();
      $entrata_old['docenteGiustifica'] = $entrata->getDocenteGiustifica();
      $entrata->setDocente($this->getUser());
    } else {
      // nuovo
      $entrata = (new Entrata())
        ->setData($data_obj)
        ->setAlunno($alunno)
        ->setValido(false)
        ->setDocente($this->getUser());
      // imposta ora
      $ora = new \DateTime();
      if ($data != date('Y-m-d') || $ora->format('H:i:00') < $orario[0]['inizio'] ||
          $ora->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
        // data non odierna o ora attuale fuori da orario
        $ora = \DateTime::createFromFormat('H:i:s', $orario[0]['inizio']);
      }
      $entrata->setOra($ora);
      if ($this->getUser() instanceof Staff && substr($referer, 0, 16) == '/staff/autorizza') {
        // precompila ora e testo
        $ora = \DateTime::createFromFormat('H:i:s', $orario[0]['inizio']);
        $ora->modify('+60 minutes');
        $entrata->setOra($ora);
        $sex = ($alunno->getSesso() == 'M' ? 'o' : 'a');
        $nota = "L'alunn$sex ".$alunno->getCognome().' '.$alunno->getNome()." è autorizzat$sex ad entrare alla seconda ora";
        $entrata->setNote($nota);
      }
      $em->persist($entrata);
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, ($cattedra ? $cattedra->getMateria() : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('entrata_edit', FormType::class, $entrata)
      ->add('ora', TimeType::class, array('label' => 'label.ora_entrata',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true));
    if ($this->getUser() instanceof Staff) {
      $form = $form
        ->add('valido', ChoiceType::class, array('label' => 'label.conteggio_entrate',
          'choices' => ['label.si' => true, 'label.no' => false],
          'expanded' => true,
          'multiple' => false,
          'label_attr' => ['class' => 'radio-inline'],
          'required' => true))
        ->add('referer', HiddenType::class, array(
          'data' => $referer,
          'mapped' => false));
    }
    $form = $form
      ->add('note', TextareaType::class, array('label' => 'label.note',
        'trim' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']));
    if (isset($entrata_old)) {
      $form = $form
        ->add('delete', SubmitType::class, array('label' => 'label.delete',
          'attr' => ['widget' => 'gs-button-inline', 'class' => 'btn-danger']));
    }
    $form = $form
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl($redirect)."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // imposta redirect
      if ($this->getUser() instanceof Staff && substr($form->get('referer')->getData(), 0, 16) == '/staff/autorizza') {
        // pagina dello staff
        $redirect = 'staff_autorizza';
        $template = 'ruolo_staff/entrata.html.twig';
        $titolo_pagina = 'page.staff_autorizza';
      } else {
        // default
        $redirect = 'lezioni_assenze_quadro';
        $template = 'lezioni/entrata_edit.html.twig';
        $titolo_pagina = 'page.lezioni_assenze';
      }
      if (!isset($entrata_old) && isset($request->request->get('entrata_edit')['delete'])) {
        // ritardo non esiste, niente da fare
        return $this->redirectToRoute($redirect);
      } elseif ($form->get('ora')->getData()->format('H:i:00') <= $orario[0]['inizio'] ||
                $form->get('ora')->getData()->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
        // ora fuori dai limiti
        $form->get('ora')->addError(new FormError($this->get('translator')->trans('field.time', [], 'validators')));
      } elseif ($form->isValid()) {
        if (isset($entrata_old) && $form->get('delete')->isClicked()) {
          // cancella ritardo esistente
          $id_entrata = $entrata->getId();
          $em->remove($entrata);
        } else {
          // controlla ritardo breve
          $inizio = \DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 '.$orario[0]['inizio']);
          $inizio->modify('+' . $session->get('/CONFIG/SCUOLA/ritardo_breve', 0) . 'minutes');
          if ($form->get('ora')->getData() <= $inizio) {
            // ritardo breve: giustificazione automatica (non imposta docente)
            $entrata
              ->setGiustificato($data_obj)
              ->setDocenteGiustifica(null)
              ->setValido(false);
          }
          // controlla se risulta assente
          $assenza = $em->getRepository('AppBundle:Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
          if ($assenza) {
            // cancella assenza
            $id_assenza = $assenza->getId();
            $em->remove($assenza);
          }
        }
        // ok: memorizza dati
        $em->flush();
        // ricalcola ore assenze
        $reg->ricalcolaOreAlunno($data_obj, $alunno);
        // log azione
        if (isset($entrata_old) && $form->get('delete')->isClicked()) {
          // log cancella
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Cancella entrata', __METHOD__, array(
            'Entrata' => $id_entrata,
            'Alunno' => $entrata->getAlunno()->getId(),
            'Data' => $entrata->getData()->format('Y-m-d'),
            'Ora' => $entrata->getOra()->format('H:i'),
            'Note' => $entrata->getNote(),
            'Valido' => $entrata->getValido(),
            'Giustificato' => ($entrata->getGiustificato() ? $entrata->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $entrata->getDocente()->getId(),
            'DocenteGiustifica' => ($entrata->getDocenteGiustifica() ? $entrata->getDocenteGiustifica()->getId() : null)
            ));
        } elseif (isset($entrata_old)) {
          // log modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Modifica entrata', __METHOD__, array(
            'Entrata' => $entrata->getId(),
            'Ora' => $entrata_old['ora']->format('H:i'),
            'Note' => $entrata_old['note'],
            'Valido' => $entrata_old['valido'],
            'Giustificato' => ($entrata_old['giustificato'] ? $entrata_old['giustificato']->format('Y-m-d') : null),
            'Docente' => $entrata_old['docente']->getId(),
            'DocenteGiustifica' => ($entrata_old['docenteGiustifica'] ? $entrata_old['docenteGiustifica'] ->getId() : null)
            ));
        } else {
          // log nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Crea entrata', __METHOD__, array(
            'Entrata' => $entrata->getId()
            ));
        }
        if (isset($id_assenza)) {
          // log cancella assenza
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Cancella assenza', __METHOD__, array(
            'Assenza' => $id_assenza,
            'Alunno' => $assenza->getAlunno()->getId(),
            'Data' => $assenza->getData()->format('Y-m-d'),
            'Giustificato' => ($assenza->getGiustificato() ? $assenza->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $assenza->getDocente()->getId(),
            'DocenteGiustifica' => ($assenza->getDocenteGiustifica() ? $assenza->getDocenteGiustifica()->getId() : null)
            ));
        }
        // redirezione
        return $this->redirectToRoute($redirect);
      }
    }
    // mostra la pagina di risposta
    return $this->render($template, array(
      'pagina_titolo' => $titolo_pagina,
      'form' => $form->createView(),
      'form_title' => (isset($entrata_old) ? 'title.modifica_entrata' : 'title.nuova_entrata'),
      'label' => $label,
    ));
  }

  /**
   * Aggiunge, modifica o elimina un'usciata anticipata
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/uscita/{cattedra}/{classe}/{data}/{alunno}", name="lezioni_assenze_uscita",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "alunno": "\d+"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function uscitaAction(Request $request, EntityManagerInterface $em, SessionInterface $session, RegistroUtil $reg,
                                LogHandler $dblogger, $cattedra, $classe, $data, $alunno) {
    // inizializza
    $label = array();
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $em->getRepository('AppBundle:Cattedra')->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // supplenza
      $cattedra = null;
    }
    // imposta redirect/template/info pagine
    $referer = $request->headers->get('referer');
    $referer = str_replace($request->getSchemeAndHttpHost().$request->getBaseUrl(), '', $referer);
    if ($this->getUser() instanceof Staff && substr($referer, 0, 16) == '/staff/autorizza') {
      // pagina dello staff
      $redirect = 'staff_autorizza';
      $template = 'ruolo_staff/uscita.html.twig';
      $titolo_pagina = 'page.staff_autorizza';
    } else {
      // default
      $redirect = 'lezioni_assenze_quadro';
      $template = 'lezioni/uscita_edit.html.twig';
      $titolo_pagina = 'page.lezioni_assenze';
    }
    // controlla classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla alunno
    $alunno = $em->getRepository('AppBundle:Alunno')->findOneBy(['id' => $alunno, 'classe' => $classe]);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // legge prima/ultima ora
    $orario = $reg->orarioInData($data_obj, $classe->getSede());
    // controlla uscita
    $uscita = $em->getRepository('AppBundle:Uscita')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
    if ($uscita) {
      // edit
      $uscita_old['ora'] = $uscita->getOra();
      $uscita_old['note'] = $uscita->getNote();
      $uscita_old['valido'] = $uscita->getValido();
      $uscita_old['docente'] = $uscita->getDocente();
      $uscita->setDocente($this->getUser());
    } else {
      // nuovo
      $uscita = (new Uscita())
        ->setData($data_obj)
        ->setAlunno($alunno)
        ->setValido(false)
        ->setDocente($this->getUser());
      // imposta ora
      $ora = new \DateTime();
      if ($data != date('Y-m-d') || $ora->format('H:i:00') < $orario[0]['inizio'] ||
          $ora->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
        // data non odierna o ora attuale fuori da orario
        $ora = \DateTime::createFromFormat('H:i:s', $orario[count($orario) - 1]['fine']);
      }
      $uscita->setOra($ora);
      if ($this->getUser() instanceof Staff && substr($referer, 0, 16) == '/staff/autorizza') {
        // precompila testo
        $sex = ($alunno->getSesso() == 'M' ? 'o' : 'a');
        $nota = "Si autorizza l'uscita anticipata dell'alunn$sex ".$alunno->getCognome().' '.$alunno->getNome().", accompagnat$sex da un genitore";
        $uscita->setNote($nota);
      }
      $em->persist($uscita);
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, ($cattedra ? $cattedra->getMateria() : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('uscita_edit', FormType::class, $uscita)
      ->add('ora', TimeType::class, array('label' => 'label.ora_uscita',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'required' => true));
    if ($this->getUser() instanceof Staff) {
      $form = $form
        ->add('valido', ChoiceType::class, array('label' => 'label.conteggio_uscite',
          'choices' => ['label.si' => true, 'label.no' => false],
          'expanded' => true,
          'multiple' => false,
          'label_attr' => ['class' => 'radio-inline'],
          'required' => true))
        ->add('referer', HiddenType::class, array(
          'data' => $referer,
          'mapped' => false));
    }
    $form = $form
      ->add('note', TextareaType::class, array('label' => 'label.note',
        'trim' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']));
    if (isset($uscita_old)) {
      $form = $form
        ->add('delete', SubmitType::class, array('label' => 'label.delete',
          'attr' => ['widget' => 'gs-button-inline', 'class' => 'btn-danger']));
    }
    $form = $form
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl($redirect)."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted()) {
      // imposta redirect
      if ($this->getUser() instanceof Staff && substr($form->get('referer')->getData(), 0, 16) == '/staff/autorizza') {
        // pagina dello staff
        $redirect = 'staff_autorizza';
        $template = 'ruolo_staff/uscita.html.twig';
        $titolo_pagina = 'page.staff_autorizza';
      } else {
        // default
        $redirect = 'lezioni_assenze_quadro';
        $template = 'lezioni/uscita_edit.html.twig';
        $titolo_pagina = 'page.lezioni_assenze';
      }
      if (!isset($uscita_old) && isset($request->request->get('uscita_edit')['delete'])) {
        // ritardo non esiste, niente da fare
        return $this->redirectToRoute($redirect);
      } elseif ($form->get('ora')->getData()->format('H:i:00') < $orario[0]['inizio'] ||
                $form->get('ora')->getData()->format('H:i:00') >= $orario[count($orario) - 1]['fine']) {
        // ora fuori dai limiti
        $form->get('ora')->addError(new FormError($this->get('translator')->trans('field.time', [], 'validators')));
      } elseif ($form->isValid()) {
        if (isset($uscita_old) && $form->get('delete')->isClicked()) {
          // cancella ritardo esistente
          $id_uscita = $uscita->getId();
          $em->remove($uscita);
        } else {
          // controlla se risulta assente
          $assenza = $em->getRepository('AppBundle:Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
          if ($assenza) {
            // cancella assenza
            $id_assenza = $assenza->getId();
            $em->remove($assenza);
          }
        }
        // ok: memorizza dati
        $em->flush();
        // ricalcola ore assenze
        $reg->ricalcolaOreAlunno($data_obj, $alunno);
        // log azione
        if (isset($uscita_old) && $form->get('delete')->isClicked()) {
          // cancella
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Cancella uscita', __METHOD__, array(
            'Uscita' => $id_uscita,
            'Alunno' => $uscita->getAlunno()->getId(),
            'Data' => $uscita->getData()->format('Y-m-d'),
            'Ora' => $uscita->getOra()->format('H:i'),
            'Note' => $uscita->getNote(),
            'Valido' => $uscita->getValido(),
            'Docente' => $uscita->getDocente()->getId()
            ));
        } elseif (isset($uscita_old)) {
          // modifica
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Modifica uscita', __METHOD__, array(
            'Uscita' => $uscita->getId(),
            'Ora' => $uscita_old['ora']->format('H:i'),
            'Note' => $uscita_old['note'],
            'Valido' => $uscita_old['valido'],
            'Docente' => $uscita_old['docente']->getId()
            ));
        } else {
          // nuovo
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Crea uscita', __METHOD__, array(
            'Uscita' => $uscita->getId()
            ));
        }
        if (isset($id_assenza)) {
          // cancella assenza
          $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Cancella assenza', __METHOD__, array(
            'Assenza' => $id_assenza,
            'Alunno' => $assenza->getAlunno()->getId(),
            'Data' => $assenza->getData()->format('Y-m-d'),
            'Giustificato' => ($assenza->getGiustificato() ? $assenza->getGiustificato()->format('Y-m-d') : null),
            'Docente' => $assenza->getDocente()->getId(),
            'DocenteGiustifica' => ($assenza->getDocenteGiustifica() ? $assenza->getDocenteGiustifica()->getId() : null)
            ));
        }
        // redirezione
        return $this->redirectToRoute($redirect);
      }
    }
    // mostra la pagina di risposta
    return $this->render($template, array(
      'pagina_titolo' => $titolo_pagina,
      'form' => $form->createView(),
      'form_title' => (isset($uscita_old) ? 'title.modifica_uscita' : 'title.nuova_uscita'),
      'label' => $label,
    ));
  }

  /**
   * Aggiunge, modifica o elimina un ritardo
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   * @param int $alunno Identificativo dell'alunno
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/giustifica/{cattedra}/{classe}/{data}/{alunno}", name="lezioni_assenze_giustifica",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d", "alunno": "\d+"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function giustificaAction(Request $request, EntityManagerInterface $em, RegistroUtil $reg,
                                    LogHandler $dblogger, $cattedra, $classe, $data, $alunno) {
    // inizializza
    $label = array();
    $settimana = ['Domenica', 'Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato'];
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $em->getRepository('AppBundle:Cattedra')->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // supplenza
      $cattedra = null;
    }
    // controlla classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla alunno
    $alunno = $em->getRepository('AppBundle:Alunno')->find($alunno);
    if (!$alunno) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), $alunno, $classe, ($cattedra ? $cattedra->getMateria() : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // assenze da giustificare
    $giustifica = $reg->assenzeRitardiDaGiustificare($data_obj, $alunno, $classe);
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    $label['alunno'] = $alunno->getCognome().' '.$alunno->getNome();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('giustifica_edit', FormType::class)
      ->add('assenze', ChoiceType::class, array('label' => 'label.assenze',
        'choices' => $giustifica['assenze'],
        'choice_label' => function ($value, $key, $index) use ($settimana) {
            return $settimana[$value->getData()->format('w')].' '.$value->getData()->format('d/m/Y');
          },
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false))
      ->add('ritardi', ChoiceType::class, array('label' => 'label.ritardi',
        'choices' => $giustifica['ritardi'],
        'choice_label' => function ($value, $key, $index) use ($settimana) {
            return $settimana[$value->getData()->format('w')].' '.$value->getData()->format('d/m/Y').
              ' ore '.$value->getOra()->format('H:i');
          },
        'choice_value' => 'id',
        'label_attr' => ['class' => 'gs-checkbox'],
        'choice_translation_domain' => false,
        'expanded' => true,
        'multiple' => true,
        'required' => false))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('lezioni_assenze_quadro')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      foreach ($form->get('assenze')->getData() as $ass) {
        $ass
          ->setGiustificato($data_obj)
          ->setDocenteGiustifica($this->getUser());
      }
      foreach ($form->get('ritardi')->getData() as $rit) {
        $rit
          ->setGiustificato($data_obj)
          ->setDocenteGiustifica($this->getUser());
      }
      // ok: memorizza dati
      $em->flush();
      // log azione
      foreach ($form->get('assenze')->getData() as $ass) {
        // giustifica assenza
        $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Giustifica assenza', __METHOD__, array(
          'Assenza' => $ass->getId()
          ));
      }
      foreach ($form->get('ritardi')->getData() as $rit) {
        // giustifica ritardo
        $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Giustifica ritardo', __METHOD__, array(
          'Entrata' => $rit->getId()
          ));
      }
      // redirezione
      return $this->redirectToRoute('lezioni_assenze_quadro');
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/giustifica_edit.html.twig', array(
      'pagina_titolo' => 'page.lezioni_assenze',
      'form' => $form->createView(),
      'form_title' => 'title.giustifica',
      'label' => $label,
    ));
  }

  /**
   * Gestione dell'appello
   *
   * @param Request $request Pagina richiesta
   * @param EntityManagerInterface $em Gestore delle entità
   * @param SessionInterface $session Gestore delle sessioni
   * @param RegistroUtil $reg Funzioni di utilità per il registro
   * @param LogHandler $dblogger Gestore dei log su database
   * @param int $cattedra Identificativo della cattedra (nullo se supplenza)
   * @param int $classe Identificativo della classe
   * @param string $data Data del giorno (AAAA-MM-GG)
   *
   * @return Response Pagina di risposta
   *
   * @Route("/lezioni/assenze/appello/{cattedra}/{classe}/{data}", name="lezioni_assenze_appello",
   *    requirements={"cattedra": "\d+", "classe": "\d+", "data": "\d\d\d\d-\d\d-\d\d"})
   * @Method({"GET","POST"})
   *
   * @Security("has_role('ROLE_DOCENTE')")
   */
  public function appelloAction(Request $request, EntityManagerInterface $em, SessionInterface $session, RegistroUtil $reg,
                                 LogHandler $dblogger, $cattedra, $classe, $data) {
    // inizializza
    $label = array();
    if ($cattedra > 0) {
      // cattedra definita
      $cattedra = $em->getRepository('AppBundle:Cattedra')->find($cattedra);
      if (!$cattedra) {
        // errore
        throw $this->createNotFoundException('exception.id_notfound');
      }
    } else {
      // supplenza
      $cattedra = null;
    }
    // controlla classe
    $classe = $em->getRepository('AppBundle:Classe')->find($classe);
    if (!$classe) {
      // errore
      throw $this->createNotFoundException('exception.id_notfound');
    }
    // controlla data
    $data_obj = \DateTime::createFromFormat('Y-m-d', $data);
    $errore = $reg->controlloData($data_obj, $classe->getSede());
    if ($errore) {
      // errore: festivo
      throw $this->createNotFoundException('exception.invalid_params');
    }
    // controlla permessi
    if (!$reg->azioneAssenze($data_obj, $this->getUser(), null, $classe, ($cattedra ? $cattedra->getMateria() : null))) {
      // errore: azione non permessa
      throw $this->createNotFoundException('exception.not_allowed');
    }
    // elenco di alunni per l'appello
    $elenco = $reg->elencoAppello($data_obj, $classe, ($cattedra && $cattedra->getMateria()->getTipo() == 'R'));
    // dati in formato stringa
    $formatter = new \IntlDateFormatter('it_IT', \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
    $formatter->setPattern('EEEE d MMMM yyyy');
    $label['data'] =  $formatter->format($data_obj);
    $label['docente'] = $this->getUser()->getNome().' '.$this->getUser()->getCognome();
    $label['classe'] = $classe->getAnno()."ª ".$classe->getSezione();
    // form di inserimento
    $form = $this->container->get('form.factory')->createNamedBuilder('assenze_appello', FormType::class)
      ->add('lista', CollectionType::class, array('label' => false,
        'data' => $elenco,
        'entry_type' => AppelloType::class,
        'entry_options' => array('label' => false),
        ))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
        'onclick' => "location.href='".$this->generateUrl('lezioni_assenze_quadro')."'"]))
      ->getForm();
    $form->handleRequest($request);
    if ($form->isSubmitted() && $form->isValid()) {
      // imposta assenze/ritardi
      $log['assenza_create'] = array();
      $log['assenza_delete'] = array();
      $log['entrata_create'] = array();
      $log['entrata_edit'] = array();
      $log['entrata_delete'] = array();
      $log['uscita_delete'] = array();
      $orario = $reg->orarioInData($data_obj, $classe->getSede());
      $alunni_assenza = array();
      foreach ($form->get('lista')->getData() as $key=>$appello) {
        $alunno = $em->getRepository('AppBundle:Alunno')->find($appello->getId());
        if (!$alunno) {
          // alunno non esiste, salta
          continue;
        }
        $alunni_assenza[] = $alunno;
        switch ($appello->getPresenza()) {
          case 'A':   // assente
            // controlla se assenza esiste
            $assenza = $em->getRepository('AppBundle:Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if (!$assenza) {
              // inserisce nuova assenza
              $assenza = (new Assenza())
                ->setData($data_obj)
                ->setAlunno($alunno)
                ->setDocente($this->getUser());
              $em->persist($assenza);
              $log['assenza_create'][] = $assenza;
            }
            // controlla esistenza ritardo
            $entrata = $em->getRepository('AppBundle:Entrata')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($entrata) {
              // rimuove ritardo
              $log['entrata_delete'][] = array($entrata->getId(), $entrata);
              $em->remove($entrata);
            }
            // controlla esistenza uscita
            $uscita = $em->getRepository('AppBundle:Uscita')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($uscita) {
              // rimuove uscita
              $log['uscita_delete'][] = array($uscita->getId(), $uscita);
              $em->remove($uscita);
            }
            break;
          case 'P':   // presente
            // controlla esistenza assenza
            $assenza = $em->getRepository('AppBundle:Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($assenza) {
              // rimuove assenza
              $log['assenza_delete'][] = array($assenza->getId(), $assenza);
              $em->remove($assenza);
            }
            // controlla esistenza ritardo
            $entrata = $em->getRepository('AppBundle:Entrata')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($entrata) {
              // rimuove ritardo
              $log['entrata_delete'][] = array($entrata->getId(), $entrata);
              $em->remove($entrata);
            }
            break;
          case 'R':   // ritardo
            // validazione orario
            if ($appello->getOra()->format('H:i:00') <= $orario[0]['inizio'] ||
                $appello->getOra()->format('H:i:00') > $orario[count($orario) - 1]['fine']) {
              // errore su orario
              $form->get('lista')[$key]->get('ora')->addError(new FormError($this->get('translator')->trans('field.time', [], 'validators')));
              continue;
            }
            // controlla esistenza ritardo
            $entrata = $em->getRepository('AppBundle:Entrata')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($entrata) {
              if ($entrata->getOra()->format('H:i') != $appello->getOra()->format('H:i')) {
                // modifica
                $log['entrata_edit'][] = array($entrata->getId(), $entrata->getAlunno()->getId(),
                  $entrata->getOra()->format('H:i'), $entrata->getNote(), $entrata->getGiustificato(),
                  $entrata->getDocente()->getId(), $entrata->getDocenteGiustifica());
                $entrata->setOra($appello->getOra());
                $entrata->setDocente($this->getUser());
                // controlla ritardo breve
                $inizio = \DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 '.$orario[0]['inizio']);
                $inizio->modify('+' . $session->get('/CONFIG/SCUOLA/ritardo_breve', 0) . 'minutes');
                if ($appello->getOra() <= $inizio) {
                  // ritardo breve: giustificazione automatica (non imposta docente)
                  $entrata->setGiustificato($data_obj);
                  $entrata->setDocenteGiustifica(null);
                }
              }
            } else {
              // inserisce ritardo
              $entrata = (new Entrata())
                ->setData($data_obj)
                ->setAlunno($alunno)
                ->setDocente($this->getUser())
                ->setOra($appello->getOra())
                ->setValido(false);
              // controlla ritardo breve
              $inizio = \DateTime::createFromFormat('Y-m-d H:i:s', '1970-01-01 '.$orario[0]['inizio']);
              $inizio->modify('+' . $session->get('/CONFIG/SCUOLA/ritardo_breve', 0) . 'minutes');
              if ($appello->getOra() <= $inizio) {
                // ritardo breve: giustificazione automatica (non imposta docente)
                $entrata->setGiustificato($data_obj);
                $entrata->setDocenteGiustifica(null);
              }
              $em->persist($entrata);
              $log['entrata_create'][] = $entrata;
            }
            // controlla esistenza assenza
            $assenza = $em->getRepository('AppBundle:Assenza')->findOneBy(['alunno' => $alunno, 'data' => $data_obj]);
            if ($assenza) {
              // rimuove assenza
              $log['assenza_delete'][] = array($assenza->getId(), $assenza);
              $em->remove($assenza);
            }
            break;
        }
      }
      if ($form->isValid()) {
        // ok: memorizza dati
        $em->flush();
        // ricalcola ore assenze
        foreach ($alunni_assenza as $alu) {
          $reg->ricalcolaOreAlunno($data_obj, $alu);
        }
        // log azione
        $dblogger->write($this->getUser(), $request->getClientIp(), 'ASSENZE', 'Appello', __METHOD__, array(
          'Data' => $data,
          'Assenze create' => implode(', ', array_map(function ($e) {
              return $e->getId();
            }, $log['assenza_create'])),
          'Assenze cancellate' => implode(', ', array_map(function ($e) {
              return '[Assenza: '.$e[0].', Alunno: '.$e[1]->getAlunno()->getId().
                ', Giustificato: '.($e[1]->getGiustificato() ? $e[1]->getGiustificato()->format('Y-m-d') : '').
                ', Docente: '.$e[1]->getDocente()->getId().
                ', DocenteGiustifica: '.($e[1]->getDocenteGiustifica() ? $e[1]->getDocenteGiustifica()->getId() : '').']';
            }, $log['assenza_delete'])),
          'Entrate create' => implode(', ', array_map(function ($e) {
              return $e->getId();
            }, $log['entrata_create'])),
          'Entrate modificate' => implode(', ', array_map(function ($e) {
              return '[Entrata: '.$e[0].', Alunno: '.$e[1].', Ora: '.$e[2].
                ', Note: "'.$e[3].'"'.
                ', Giustificato: '.($e[4] ? $e[4]->format('Y-m-d') : '').
                ', Docente: '.$e[5].
                ', DocenteGiustifica: '.($e[6] ? $e[6]->getId() : '').']';
            }, $log['entrata_edit'])),
          'Entrate cancellate' => implode(', ', array_map(function ($e) {
              return '[Entrata: '.$e[0].', Alunno: '.$e[1]->getAlunno()->getId().
                ', Ora: '.$e[1]->getOra()->format('H:i').
                ', Note: "'.$e[1]->getNote().'"'.
                ', Giustificato: '.($e[1]->getGiustificato() ? $e[1]->getGiustificato()->format('Y-m-d') : '').
                ', Docente: '.$e[1]->getDocente()->getId().
                ', DocenteGiustifica: '.($e[1]->getDocenteGiustifica() ? $e[1]->getDocenteGiustifica()->getId() : '').']';
            }, $log['entrata_delete'])),
          'Uscite cancellate' => implode(', ', array_map(function ($e) {
              return '[Uscita: '.$e[0].', Alunno: '.$e[1]->getAlunno()->getId().
                ', Ora: '.$e[1]->getOra()->format('H:i').
                ', Note: "'.$e[1]->getNote().'"'.
                ', Docente: '.$e[1]->getDocente()->getId();
            }, $log['uscita_delete']))
          ));
        // redirezione
        return $this->redirectToRoute('lezioni_assenze_quadro');
      }
    }
    // mostra la pagina di risposta
    return $this->render('lezioni/appello.html.twig', array(
      'pagina_titolo' => 'page.lezioni_assenze',
      'form' => $form->createView(),
      'form_title' => 'title.appello',
      'label' => $label,
    ));
  }

}

