<?php
namespace Cnab\Format;

class Field {
	private $cnabLinha;
	public $nome;
	public  $format;
	private $valor_decoded;
	private $valor_encoded = null;
	
	public $pos_start;
	public $pos_end;
	public $length;
	public $options;
	
	public function __construct(Linha $linha, $nome, $format, $pos_start, $pos_end, $options)
	{
		if(!Picture::validarFormato($format))
			throw new \InvalidArgumentException("'$format' is not a valid format on $nome");
		
		$this->options = $options;
		$this->nome         = $nome;
		$this->cnabLinha    = $linha;
		$this->format       = $format;
		$this->pos_start    = $pos_start;
		$this->pos_end      = $pos_end;
		$this->length       = ($pos_end + 1) - $pos_start;

		$p_length = Picture::getLength($this->format);
		if($p_length > $this->length)
			throw new \Exception("Picture length of '$this->nome' need more positions than  $pos_start : $pos_end");
		else if($p_length < $this->length)
			throw new \Exception("Picture length of '$this->nome' need less positions than  $pos_start : $pos_end");
	}

	public function set($valor)
	{
		if($valor === false || is_null($valor))
			throw new \Exception("'$this->nome' dont be false or null ($this->pos_start, $this->pos_end)");
			
		$this->valor_decoded = $valor;
		
		try 
		{
			// DD-803: número de inscrição com a forma do CNPJ alfanumérico (12 posições com letra e 2 dígitos). O resto
			// segue a picture, que recusa letra como sempre: o placeholder sem dígito (XXXXXXXXXXX, FR-034) e o CNPJ
			// incompleto, que não pode ser completado (FR-016).
			if ($this->isNumeroInscricao() && $this->isCnpjAlfanumerico($valor))
				$this->valor_encoded = Picture::encodeDocumento($valor, $this->format);
			else
				$this->valor_encoded = Picture::encode($valor, $this->format, $this->options);	
		}
		catch(\Exception $e)
		{
			trigger_error("Error in field '$this->nome': " . $e->getMessage(), E_USER_NOTICE);
			throw $e; // para exibir o backtrace
		}
		
		$len = strlen($this->valor_encoded);
		if($len != $this->length)
			throw new \Exception("'$this->nome' have length '$len', but field need length $this->length");
	}
	
	/**
	 * DD-803: os campos de número de inscrição dos layouts (empresa, sacado e sacador) terminam todos assim.
	 */
	private function isNumeroInscricao()
	{
		return substr($this->nome, -strlen('numero_inscricao')) === 'numero_inscricao';
	}

	private function isCnpjAlfanumerico($valor)
	{
		$canonico = preg_replace('/[^0-9A-Z]/', '', strtoupper((string) $valor));

		return preg_match('/[A-Z]/', $canonico) && preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $canonico);
	}

	public function getValue()
	{		
		return $this->valor_decoded;
	}

	public function getName()
	{
		return $this->nome;
	}
	
	public function getEncoded()
	{
		if(is_null($this->valor_encoded))
			throw new \Exception("'$this->nome' dont be null, need to set any value");
		return $this->valor_encoded;
	}
}