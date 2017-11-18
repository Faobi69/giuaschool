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


namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;


/**
 * Esito - entità
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\EsitoRepository")
 * @ORM\Table(name="gs_esito", uniqueConstraints={@ORM\UniqueConstraint(columns={"scrutinio_id","alunno_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"scrutinio","alunno"}, message="field.unique")
 */
class Esito {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per l'esito
   *
   * @ORM\Column(type="integer")
   * @ORM\Id
   * @ORM\GeneratedValue(strategy="AUTO")
   */
  private $id;

  /**
   * @var \DateTime $modificato Ultima modifica dei dati
   *
   * @ORM\Column(type="datetime", nullable=false)
   */
  private $modificato;

  /**
   * @var string $esito Esito dello scrutinio [A=ammesso, N=non ammesso, S=sospeso, R=non scrutinato (ritirato d'ufficio), L=superamento limite assenze]
   *
   * @ORM\Column(type="string", length=1, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   * @Assert\Choice(choices={"A","N","S","R","L"}, strict=true, message="field.choice")
   */
  private $esito;

  /**
   * @var float $media Media dei voti
   *
   * @ORM\Column(type="float", precision=4, scale=2, nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $media;

  /**
   * @var integer $credito Punteggio di credito
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $credito;

  /**
   * @var integer $creditoPrecedente Punteggio di credito degli anni precedenti
   *
   * @ORM\Column(name="credito_precedente", type="integer", nullable=true)
   */
  private $creditoPrecedente;

  /**
   * @var string $giudizio Giudizio di non ammissione o di ammissione all'esame
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $giudizio;

  /**
   * @var Scrutinio $scrutinio Scrutinio a cui si riferisce l'esito
   *
   * @ORM\ManyToOne(targetEntity="Scrutinio")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $scrutinio;

  /**
   * @var Alunno $alunno Alunno a cui si attribuisce l'esito
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;


  //==================== EVENTI ORM ====================

  /**
   * Simula un trigger onCreate/onUpdate
   *
   * @ORM\PrePersist
   * @ORM\PreUpdate
   */
  public function onChangeTrigger() {
    // aggiorna data/ora di modifica
    $this->modificato = new \DateTime();
  }


  //==================== METODI SETTER/GETTER ====================

  /**
   * Restituisce l'identificativo univoco per l'esito
   *
   * @return integer Identificativo univoco
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Restituisce la data/ora dell'ultima modifica dei dati
   *
   * @return \DateTime Data/ora dell'ultima modifica
   */
  public function getModificato() {
    return $this->modificato;
  }

  /**
   * Restituisce l'esito dello scrutinio [A=ammesso, N=non ammesso, S=sospeso, R=non scrutinato (ritirato d'ufficio), L=superamento limite assenze]
   *
   * @return string Esito dello scrutinio
   */
  public function getEsito() {
    return $this->esito;
  }

  /**
   * Modifica l'esito dello scrutinio [A=ammesso, N=non ammesso, S=sospeso, R=non scrutinato (ritirato d'ufficio), L=superamento limite assenze]
   *
   * @param string $esito Esito dello scrutinio
   *
   * @return Esito Oggetto Esito
   */
  public function setEsito($esito) {
    $this->esito = $esito;
    return $this;
  }

  /**
   * Restituisce la media dei voti
   *
   * @return float Media dei voti
   */
  public function getMedia() {
    return $this->media;
  }

  /**
   * Modifica la media dei voti
   *
   * @param float $media Media dei voti
   *
   * @return Esito Oggetto Esito
   */
  public function setMedia($media) {
    $this->media = $media;
    return $this;
  }

  /**
   * Restituisce il punteggio di credito
   *
   * @return integer Punteggio di credito
   */
  public function getCredito() {
    return $this->credito;
  }

  /**
   * Modifica il punteggio di credito
   *
   * @param integer $credito Punteggio di credito
   *
   * @return Esito Oggetto Esito
   */
  public function setCredito($credito) {
    $this->credito = $credito;
    return $this;
  }

  /**
   * Restituisce il punteggio di credito degli anni precedenti
   *
   * @return integer Punteggio di credito degli anni precedenti
   */
  public function getCreditoPrecedente() {
    return $this->creditoPrecedente;
  }

  /**
   * Modifica il punteggio di credito degli anni precedenti
   *
   * @param integer $creditoPrecedente Punteggio di credito degli anni precedenti
   *
   * @return Esito Oggetto Esito
   */
  public function setCreditoPrecedente($creditoPrecedente) {
    $this->creditoPrecedente = $creditoPrecedente;
    return $this;
  }

  /**
   * Restituisce il giudizio di non ammissione o di ammissione all'esame
   *
   * @return string Giudizio di non ammissione o di ammissione all'esame
   */
  public function getGiudizio() {
    return $this->giudizio;
  }

  /**
   * Modifica il giudizio di non ammissione o di ammissione all'esame
   *
   * @param string $giudizio Giudizio di non ammissione o di ammissione all'esame
   *
   * @return Esito Oggetto Esito
   */
  public function setGiudizio($giudizio) {
    $this->giudizio = $giudizio;
    return $this;
  }

  /**
   * Restituisce lo scrutinio a cui si riferisce l'esito
   *
   * @return Scrutinio Scrutinio a cui si riferisce l'esito
   */
  public function getScrutinio() {
    return $this->scrutinio;
  }

  /**
   * Modifica lo scrutinio a cui si riferisce l'esito
   *
   * @param Scrutinio $scrutinio Scrutinio a cui si riferisce l'esito
   *
   * @return Esito Oggetto Esito
   */
  public function setScrutinio(Scrutinio $scrutinio) {
    $this->scrutinio = $scrutinio;
    return $this;
  }

  /**
   * Restituisce l'alunno a cui si attribuisce l'esito
   *
   * @return Alunno Alunno a cui si attribuisce l'esito
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui si attribuisce l'esito
   *
   * @param Alunno $alunno Alunno a cui si attribuisce l'esito
   *
   * @return Esito Oggetto Esito
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->scrutinio.' - '.$this->alunno.': '.$this->esito;
  }

}
