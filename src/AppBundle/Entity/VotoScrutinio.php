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
 * VotoScrutinio
 *
 * @ORM\Entity(repositoryClass="AppBundle\Repository\VotoScrutinioRepository")
 * @ORM\Table(name="gs_voto_scrutinio", uniqueConstraints={@ORM\UniqueConstraint(columns={"scrutinio_id","alunno_id","materia_id"})})
 * @ORM\HasLifecycleCallbacks
 *
 * @UniqueEntity(fields={"scrutinio","alunno","materia"}, message="field.unique")
 */
class VotoScrutinio {


  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var integer $id Identificativo univoco per il voto assegnato allo scrutinio
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
   * @var integer $orale Voto per la valutazione orale
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $orale;

  /**
   * @var integer $scritto Voto per la valutazione scritta
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $scritto;

  /**
   * @var integer $pratico Voto per la valutazione pratica
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $pratico;

  /**
   * @var integer $unico Voto per la valutazione unica
   *
   * @ORM\Column(type="integer", nullable=true)
   */
  private $unico;

  /**
   * @var string $debito Argomenti per il recupero del debito
   *
   * @ORM\Column(type="text", nullable=true)
   */
  private $debito;

  /**
   * @var string $recupero Modalità di recupero del debito [A=autonomo, C=corso, S=sportello]
   *
   * @ORM\Column(type="string", length=1, nullable=true)
   *
   * @Assert\Choice(choices={"A","C","S"}, strict=true, message="field.choice")
   */
  private $recupero;

  /**
   * @var integer $assenze Numero di ore di assenza nel periodo
   *
   * @ORM\Column(type="integer", nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $assenze;

  /**
   * @var Scrutinio $scrutinio Scrutinio a cui si riferisce il voto
   *
   * @ORM\ManyToOne(targetEntity="Scrutinio")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $scrutinio;

  /**
   * @var Alunno $alunno Alunno a cui si attribuisce il voto
   *
   * @ORM\ManyToOne(targetEntity="Alunno")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $alunno;

  /**
   * @var Materia $materia Materia del voto
   *
   * @ORM\ManyToOne(targetEntity="Materia")
   * @ORM\JoinColumn(nullable=false)
   *
   * @Assert\NotBlank(message="field.notblank")
   */
  private $materia;


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
   * Restituisce l'identificativo univoco per il voto
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
   * Restituisce il voto per la valutazione orale
   *
   * @return integer Voto per la valutazione orale
   */
  public function getOrale() {
    return $this->orale;
  }

  /**
   * Modifica il voto per la valutazione orale
   *
   * @param integer $orale Voto per la valutazione orale
   *
   * @return VotoScrutinio Oggetto VotoScrutinio
   */
  public function setOrale($orale) {
    $this->orale = $orale;
    return $this;
  }

  /**
   * Restituisce il voto per la valutazione scritta
   *
   * @return integer Voto per la valutazione scritta
   */
  public function getScritto() {
    return $this->scritto;
  }

  /**
   * Modifica il voto per la valutazione scritta
   *
   * @param integer $scritto Voto per la valutazione scritta
   *
   * @return VotoScrutinio Oggetto VotoScrutinio
   */
  public function setScritto($scritto) {
    $this->scritto = $scritto;
    return $this;
  }

  /**
   * Restituisce il voto per la valutazione pratica
   *
   * @return integer Voto per la valutazione pratica
   */
  public function getPratico() {
    return $this->pratico;
  }

  /**
   * Modifica il voto per la valutazione pratica
   *
   * @param integer $pratico Voto per la valutazione pratica
   *
   * @return VotoScrutinio Oggetto VotoScrutinio
   */
  public function setPratico($pratico) {
    $this->pratico = $pratico;
    return $this;
  }

  /**
   * Restituisce il voto per la valutazione unica
   *
   * @return integer Voto per la valutazione unica
   */
  public function getUnico() {
    return $this->unico;
  }

  /**
   * Modifica il voto per la valutazione unica
   *
   * @param integer $unico Voto per la valutazione unica
   *
   * @return VotoScrutinio Oggetto VotoScrutinio
   */
  public function setUnico($unico) {
    $this->unico = $unico;
    return $this;
  }

  /**
   * Restituisce gli argomenti per il recupero del debito
   *
   * @return string Argomenti per il recupero del debito
   */
  public function getDebito() {
    return $this->debito;
  }

  /**
   * Modifica gli argomenti per il recupero del debito
   *
   * @param string $debito Argomenti per il recupero del debito
   *
   * @return VotoScrutinio Oggetto VotoScrutinio
   */
  public function setDebito($debito) {
    $this->debito = $debito;
    return $this;
  }

  /**
   * Restituisce la modalità di recupero del debito [A=autonomo, C=corso, S=sportello]
   *
   * @return string Modalità di recupero del debito
   */
  public function getRecupero() {
    return $this->recupero;
  }

  /**
   * Modifica la modalità di recupero del debito [A=autonomo, C=corso, S=sportello]
   *
   * @param string $recupero Modalità di recupero del debito
   *
   * @return VotoScrutinio Oggetto VotoScrutinio
   */
  public function setRecupero($recupero) {
    $this->recupero = $recupero;
    return $this;
  }

  /**
   * Restituisce il numero di ore di assenza nel periodo
   *
   * @return integer Numero di ore di assenza nel periodo
   */
  public function getAssenze() {
    return $this->assenze;
  }

  /**
   * Modifica il numero di ore di assenza nel periodo
   *
   * @param integer $assenze Numero di ore di assenza nel periodo
   *
   * @return VotoScrutinio Oggetto VotoScrutinio
   */
  public function setAssenze($assenze) {
    $this->assenze = $assenze;
    return $this;
  }

  /**
   * Restituisce lo scrutinio a cui si riferisce il voto
   *
   * @return Scrutinio Scrutinio a cui si riferisce il voto
   */
  public function getScrutinio() {
    return $this->scrutinio;
  }

  /**
   * Modifica lo scrutinio a cui si riferisce il voto
   *
   * @param Scrutinio $scrutinio Scrutinio a cui si riferisce il voto
   *
   * @return VotoScrutinio Oggetto VotoScrutinio
   */
  public function setScrutinio(Scrutinio $scrutinio) {
    $this->scrutinio = $scrutinio;
    return $this;
  }

  /**
   * Restituisce l'alunno a cui si attribuisce il voto
   *
   * @return Alunno Alunno a cui si attribuisce il voto
   */
  public function getAlunno() {
    return $this->alunno;
  }

  /**
   * Modifica l'alunno a cui si attribuisce il voto
   *
   * @param Alunno $alunno Alunno a cui si attribuisce il voto
   *
   * @return VotoScrutinio Oggetto VotoScrutinio
   */
  public function setAlunno(Alunno $alunno) {
    $this->alunno = $alunno;
    return $this;
  }

  /**
   * Restituisce la materia del voto
   *
   * @return Materia Materia del voto
   */
  public function getMateria() {
    return $this->materia;
  }

  /**
   * Modifica la materia del voto
   *
   * @param Materia $materia Materia del voto
   *
   * @return VotoScrutinio Oggetto VotoScrutinio
   */
  public function setMateria(Materia $materia) {
    $this->materia = $materia;
    return $this;
  }


  //==================== METODI DELLA CLASSE ====================

  /**
   * Restituisce l'oggetto rappresentato come testo
   *
   * @return string Oggetto rappresentato come testo
   */
  public function __toString() {
    return $this->materia.' - '.$this->alunno.': '.$this->orale.' '.$this->scritto.' '.$this->pratico.' '.$this->unico;
  }

}
