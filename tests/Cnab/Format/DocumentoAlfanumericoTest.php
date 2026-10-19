<?php
namespace Cnab\Tests\Format;

use Cnab\Factory;
use Cnab\Format\Picture;
use Cnab\Remessa\Cnab240\HeaderArquivo;
use Cnab\Remessa\Cnab240\SegmentoQ;

/**
 * DD-803 (CNPJ alfanumérico, IN RFB 2.229/2024): os layouts declaram os campos de número de inscrição como numéricos
 * (9(15) no pagador do segmento Q, 9(14) na empresa do header), e a picture recusava o CNPJ com letra com
 * "value '...' dont is a number".
 *
 * Nos campos de número de inscrição, o CNPJ alfanumérico (12 posições com letra e 2 dígitos) passa a ser escrito como
 * no fork do laravel-boleto: canônico (maiúsculas, sem máscara), alinhado à direita e completado com zeros, e recusado
 * se não couber. O par: CNPJ numérico e CPF saem como sempre; o que não tem a forma do CNPJ (incompleto, placeholder
 * sem dígito) e os outros campos numéricos continuam recusando letra.
 *
 * Usa o layout Sicoob 240 (banco 756) do cnab_yaml.
 */
class DocumentoAlfanumericoTest extends \PHPUnit_Framework_TestCase
{
    private function arquivo()
    {
        $factory = new Factory();

        return $factory->createRemessa('756', 'cnab240', 'sigcb');
    }

    private function codificado($linha, $campo)
    {
        foreach ($linha->getFields() as $field) {
            if ($field->nome === $campo) {
                return $field->getEncoded();
            }
        }

        $this->fail("o layout não tem o campo $campo");
    }

    public function documentosDoPagador()
    {
        return array(
            'CNPJ alfanumérico'                  => array('12ABC34501DE35', '012ABC34501DE35'),
            'CNPJ alfanumérico com máscara'      => array('12.abc.345/01de-35', '012ABC34501DE35'),
            'CNPJ numérico'                      => array('11222333000181', '011222333000181'),
            'CPF'                                => array('52998224725', '000052998224725'),
        );
    }

    /**
     * @dataProvider documentosDoPagador
     */
    public function testOPagadorSaiNoSegmentoQ($documento, $noArquivo)
    {
        $segmento = new SegmentoQ($this->arquivo());
        $segmento->sacado_numero_inscricao = $documento;

        $this->assertSame($noArquivo, $this->codificado($segmento, 'sacado_numero_inscricao'));
    }

    public function testAEmpresaComCnpjAlfanumericoSaiNoHeader()
    {
        $header = new HeaderArquivo($this->arquivo());
        $header->numero_inscricao = '12ABC34501DE35';

        $this->assertSame('12ABC34501DE35', $this->codificado($header, 'numero_inscricao'));
    }

    public function documentosForaDaFormaDoCnpj()
    {
        return array(
            'com 15 posições'                           => array('012ABC34501DE35'),
            'CNPJ alfanumérico incompleto, sem completar' => array('QA803LI000183'),
            'placeholder de CPF sem dígito'             => array('XXXXXXXXXXX'),
            'placeholder de CNPJ sem dígito'            => array('XXXXXXXXXXXXXX'),
            'letra nos dígitos verificadores'           => array('12ABC34501DEAB'),
        );
    }

    /**
     * @dataProvider documentosForaDaFormaDoCnpj
     * @expectedException Exception
     * @expectedExceptionMessage dont is a number
     */
    public function testDocumentoForaDaFormaDoCnpjERecusado($documento)
    {
        $segmento = new SegmentoQ($this->arquivo());
        $segmento->sacado_numero_inscricao = $documento;
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage dont is a number
     */
    public function testOutroCampoNumericoSegueRecusandoLetra()
    {
        $segmento = new SegmentoQ($this->arquivo());
        $segmento->cep = '38A00000';
    }

    public function testEncodeDocumentoCompletaComZerosAEsquerda()
    {
        $this->assertSame('012ABC34501DE35', Picture::encodeDocumento('12ABC34501DE35', '9(15)'));
        $this->assertSame('12ABC34501DE35', Picture::encodeDocumento('12abc34501de35', '9(14)'));
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage não cabe no campo
     */
    public function testEncodeDocumentoRecusaODocumentoQueNaoCabe()
    {
        Picture::encodeDocumento('12ABC34501DE35', '9(13)');
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testEncodeDocumentoSoValeParaCampoNumerico()
    {
        Picture::encodeDocumento('12ABC34501DE35', 'X(14)');
    }
}
