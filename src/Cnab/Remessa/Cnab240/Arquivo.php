<?php
namespace Cnab\Remessa\Cnab240;

class Arquivo implements \Cnab\Remessa\IArquivo
{
    public $headerArquivo;
    public $headerLote;
    public $detalhes = [];
    public $trailerLote;
    public $trailerArquivo;

    private $_data_gravacao;
    private $_data_geracao;
    public $banco;
    public $codigo_banco;
    public $configuracao = [];
    public $layoutVersao;
    const   QUEBRA_LINHA = "\r\n";

    public function __construct($codigo_banco, $layoutVersao = NULL)
    {
        $this->codigo_banco = $codigo_banco;
        $this->layoutVersao = $layoutVersao;
        $this->banco = \Cnab\Banco::getBanco($this->codigo_banco);
        //$this->data_gravacao = date('dmY');
    }

    public function configure(array $params)
    {
        $banco = \Cnab\Banco::getBanco($this->codigo_banco);
        $campos = [
            'data_geracao',
            'data_gravacao',
            'nome_fantasia',
            'razao_social',
            'tipo_inscricao',
            'cpf_cnpj',
            'logradouro',
            'numero',
            'bairro',
            'cidade',
            'uf',
            'cep',
        ];

        if ($this->codigo_banco == \Cnab\Banco::CEF) {
            $campos[] = 'agencia';
            $campos[] = 'agencia_dv';
            $campos[] = 'conta';
            $campos[] = 'operacao';
            $campos[] = 'codigo_cedente';
            $campos[] = 'agencia_mais_cedente_dv';
            $campos[] = 'codigo_convenio';
            $campos[] = 'numero_sequencial_arquivo';
        }

        if ($this->codigo_banco == \Cnab\Banco::SICOOB) {
            $campos[] = 'agencia';
            $campos[] = 'agencia_dv';
            $campos[] = 'codigo_cedente';
            $campos[] = 'codigo_cedente_dv';
            $campos[] = 'agencia_mais_cedente_dv';
            $campos[] = 'numero_sequencial_arquivo';
        }

        if ($this->codigo_banco == \Cnab\Banco::BRADESCO) {
            $campos[] = 'agencia';
            $campos[] = 'agencia_dv';
            $campos[] = 'codigo_cedente';
            $campos[] = 'codigo_cedente_dv';
            $campos[] = 'agencia_mais_cedente_dv';
            $campos[] = 'codigo_convenio';
            $campos[] = 'numero_sequencial_arquivo';
        }

        if ($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            $campos[] = 'agencia';
            $campos[] = 'agencia_dv';
            $campos[] = 'conta';
            $campos[] = 'conta_dv';
            $campos[] = 'operacao';
            $campos[] = 'codigo_convenio';
            $campos[] = 'codigo_carteira';
            $campos[] = 'variacao_carteira';
            $campos[] = 'numero_sequencial_arquivo';
        }

        foreach ($campos as $campo) {
            if (array_key_exists($campo, $params)) {
                if (strpos($campo, 'data_') === 0 && !($params[$campo] instanceof \DateTime)) {
                    throw new \Exception("config '$campo' need to be instance of DateTime");
                }
                $this->configuracao[$campo] = $params[$campo];
            }
            else {
                throw new \Exception('Configuração "' . $campo . '" need to be set');
            }
        }

        foreach ($campos as $key) {
            if (!array_key_exists($key, $params)) {
                throw new Exception('Configuração "' . $key . '" dont exists');
            }
        }

        $this->data_geracao = $this->configuracao['data_geracao'];
        $this->data_gravacao = $this->configuracao['data_gravacao'];

        $this->headerArquivo = new HeaderArquivo($this);
        $this->headerLote = new HeaderLote($this);
        $this->trailerLote = new TrailerLote($this);
        $this->trailerArquivo = new TrailerArquivo($this);

        $this->headerArquivo->codigo_banco = $this->banco['codigo_do_banco'];
        $this->headerArquivo->codigo_inscricao = $this->configuracao['tipo_inscricao'];
        $this->headerArquivo->numero_inscricao = $this->prepareText($this->configuracao['cpf_cnpj'], '.-/');
        $this->headerArquivo->agencia = $this->configuracao['agencia'];
        $this->headerArquivo->agencia_dv = $this->configuracao['agencia_dv'];

        if($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            $this->headerArquivo->codigo_convenio = $this->configuracao['codigo_convenio'];
            $this->headerArquivo->carteira = $this->configuracao['codigo_carteira'];
            $this->headerArquivo->variacao_carteira = $this->configuracao['variacao_carteira'];
            $this->headerArquivo->conta = $this->configuracao['conta'];
            $this->headerArquivo->conta_dv = $this->configuracao['conta_dv'];
        }

        if ($this->codigo_banco == \Cnab\Banco::SICOOB) {
            $this->headerArquivo->codigo_cedente = $this->configuracao['codigo_cedente'];
            $this->headerArquivo->codigo_cedente_dv = $this->configuracao['codigo_cedente_dv'];
            $this->headerArquivo->agencia_mais_cedente_dv = $this->configuracao['agencia_mais_cedente_dv'];
        }

        if ($this->codigo_banco == \Cnab\Banco::BRADESCO) {
            $this->headerArquivo->codigo_cedente = $this->configuracao['codigo_cedente'];
            $this->headerArquivo->codigo_cedente_dv = $this->configuracao['codigo_cedente_dv'];
            $this->headerArquivo->codigo_convenio = $this->configuracao['codigo_convenio'];
        }

        if ($this->codigo_banco == \Cnab\Banco::CEF) {
            $this->headerArquivo->codigo_cedente = $this->configuracao['codigo_cedente'];
        }

        $this->headerArquivo->nome_empresa = $this->prepareText($this->configuracao['nome_fantasia']);
        $this->headerArquivo->nome_banco = $banco['nome_do_banco'];
        $this->headerArquivo->codigo_remessa_retorno = 1;
        $this->headerArquivo->data_geracao = $this->configuracao['data_geracao'];
        $this->headerArquivo->hora_geracao = $this->configuracao['data_geracao'];
        $this->headerArquivo->numero_sequencial_arquivo = $this->configuracao['numero_sequencial_arquivo'];

        $this->headerLote->codigo_banco = $this->headerArquivo->codigo_banco;
        $this->headerLote->lote_servico = 1;
        $this->headerLote->tipo_operacao = 'R';
        $this->headerLote->codigo_inscricao = $this->headerArquivo->codigo_inscricao;
        $this->headerLote->numero_inscricao = $this->headerArquivo->numero_inscricao;
        $this->headerLote->agencia = $this->headerArquivo->agencia;
        $this->headerLote->agencia_dv = $this->headerArquivo->agencia_dv;

        if ($this->codigo_banco == \Cnab\Banco::SICOOB) {
            $this->headerLote->codigo_convenio = '';
            $this->headerLote->codigo_cedente = $this->headerArquivo->codigo_cedente;
            $this->headerLote->codigo_cedente_dv = $this->configuracao['codigo_cedente_dv'];
            $this->headerLote->agencia_mais_cedente_dv = $this->configuracao['agencia_mais_cedente_dv'];
        }

        if ($this->codigo_banco == \Cnab\Banco::BRADESCO) {
            $this->headerLote->codigo_convenio = $this->configuracao['codigo_convenio'];
            $this->headerLote->codigo_cedente = $this->headerArquivo->codigo_cedente;
            $this->headerLote->codigo_cedente_dv = $this->configuracao['codigo_cedente_dv'];
            $this->headerLote->agencia_mais_cedente_dv = '';
        }

        if ($this->codigo_banco == \Cnab\Banco::CEF) {
            $this->headerLote->codigo_convenio = $this->headerArquivo->codigo_cedente;
            $this->headerLote->codigo_cedente = $this->headerArquivo->codigo_cedente;
        }

        $this->headerLote->nome_empresa = $this->headerArquivo->nome_empresa;
        $this->headerLote->numero_sequencial_arquivo = $this->headerArquivo->numero_sequencial_arquivo;
        $this->headerLote->data_geracao = $this->headerArquivo->data_geracao;
        if ($this->codigo_banco == \Cnab\Banco::CEF) {
            $this->headerLote->tipo_servico = 2;
        }

        if ($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            $this->headerLote->codigo_convenio = $this->headerArquivo->codigo_convenio;
            $this->headerLote->carteira = $this->headerArquivo->carteira;
            $this->headerLote->variacao_carteira = $this->headerArquivo->variacao_carteira;
            $this->headerLote->conta = $this->headerArquivo->conta;
            $this->headerLote->conta_dv = $this->headerArquivo->conta_dv;
        }

        $this->trailerLote->codigo_banco = $this->headerArquivo->codigo_banco;
        $this->trailerLote->lote_servico = $this->headerLote->lote_servico;

        $this->trailerArquivo->codigo_banco = $this->headerArquivo->codigo_banco;
    }

    public function insertDetalhe(array $boleto)
    {
        $dateVencimento = $boleto['data_vencimento'] instanceof \DateTime ? $boleto['data_vencimento'] : new \DateTime($boleto['data_vencimento']);
        $dateCadastro = $boleto['data_cadastro'] instanceof \DateTime ? $boleto['data_cadastro'] : new \DateTime($boleto['data_cadastro']);
        $dateJurosMora = $boleto['data_juros_mora'] instanceof \DateTime ? $boleto['data_juros_mora'] : new \DateTime($boleto['data_juros_mora']);

        $detalhe = new Detalhe($this);

        // SEGMENTO P -------------------------------
        $detalhe->segmento_p->codigo_banco = $this->headerArquivo->codigo_banco;
        $detalhe->segmento_p->lote_servico = $this->headerLote->lote_servico;
        $detalhe->segmento_p->agencia = $this->headerArquivo->agencia;
        $detalhe->segmento_p->agencia_dv = $this->headerArquivo->agencia_dv;

        if ($this->codigo_banco == \Cnab\Banco::SICOOB || $this->codigo_banco == \Cnab\Banco::BRADESCO) {
            $detalhe->segmento_p->codigo_cedente = $this->headerArquivo->codigo_cedente;
            $detalhe->segmento_p->codigo_cedente_dv = $this->configuracao['codigo_cedente_dv'];
            $detalhe->segmento_p->agencia_mais_cedente_dv = $this->configuracao['agencia_mais_cedente_dv'];
            $detalhe->segmento_p->agencia_cobradora_dv = ' ';
        }

        if ($this->codigo_banco == \Cnab\Banco::BRADESCO) {
            $detalhe->segmento_p->codigo_cedente = $this->headerArquivo->codigo_cedente;
            $detalhe->segmento_p->modalidade_carteira = $boleto['modalidade_carteira'];
        }

        if ($this->codigo_banco == \Cnab\Banco::CEF) {
            $detalhe->segmento_p->codigo_cedente = $this->headerArquivo->codigo_cedente;
        }

        if ($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            $detalhe->segmento_p->conta = $this->headerArquivo->conta;
            $detalhe->segmento_p->conta_dv = $this->headerArquivo->conta_dv;
        }

        $detalhe->segmento_p->nosso_numero = $this->formatarNossoNumero($boleto);

        if($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            // Informar 1 – para carteira 11/12 na modalidade Simples; 2 ou 3 – para carteira 11/17 modalidade
            // Vinculada/Caucionada e carteira 31; 4 – para carteira 11/17 modalidade Descontada e carteira 51; e 7 – para
            // carteira 17 modalidade Simples.
            if($boleto['carteira'] == 17 && $boleto['codigo_carteira'] == \Cnab\CodigoCarteira::COBRANCA_SIMPLES) {
                $detalhe->segmento_p->codigo_carteira = 7;
            } else {
                $detalhe->segmento_p->codigo_carteira = $boleto['codigo_carteira'];
            }
        }

        $detalhe->segmento_p->codigo_carteira = 1; // 1 = Cobrança Simples
        if ($this->layoutVersao === 'sigcb' && $this->codigo_banco == \Cnab\Banco::CEF) {
            $detalhe->segmento_p->modalidade_carteira = '14'; // 21 = (título Sem Registro emissão CAIXA)
        }

        $detalhe->segmento_p->forma_cadastramento = $boleto['registrado'] ? 1 : 2; // 1 = Com, 2 = Sem Registro

        if ($this->codigo_banco == \Cnab\Banco::SICOOB) {
            $detalhe->segmento_p->forma_cadastramento = 0;
        }

        if ($boleto['registrado'] && $this->codigo_banco == \Cnab\Banco::CEF) {
            $this->headerLote->tipo_servico = 1;
        }
        $detalhe->segmento_p->numero_documento = $boleto['numero_documento'];
        $detalhe->segmento_p->vencimento = $dateVencimento;
        $detalhe->segmento_p->valor_titulo = $boleto['valor'];
        $detalhe->segmento_p->especie = $boleto['especie']; // 4 = Duplicata serviço
        $detalhe->segmento_p->aceite = $boleto['aceite'];
        $detalhe->segmento_p->data_emissao = $dateCadastro;
        $detalhe->segmento_p->codigo_juros_mora = $boleto['codigo_juros_mora']; // 1 = Por dia
        $detalhe->segmento_p->data_juros_mora = $dateJurosMora;
        $detalhe->segmento_p->valor_juros_mora = $boleto['juros_de_um_dia'];

        if ($boleto['valor_desconto'] > 0) {
            $detalhe->segmento_p->codigo_desconto_1 = 1; // valor fixo
            $detalhe->segmento_p->data_desconto_1 = $boleto['data_desconto'];
            $detalhe->segmento_p->valor_desconto_1 = $boleto['valor_desconto'];
        }
        else {
            $detalhe->segmento_p->codigo_desconto_1 = 0; // sem desconto
            $detalhe->segmento_p->data_desconto_1 = 0;
            $detalhe->segmento_p->valor_desconto_1 = 0;
        }

        $detalhe->segmento_p->valor_abatimento = 0;
        $detalhe->segmento_p->uso_empresa = $boleto['numero_documento'];
        $detalhe->segmento_p->codigo_protesto = 3; // 3 = Não protestar
        $detalhe->segmento_p->prazo_protesto = 0;
    
        $detalhe->segmento_p->codigo_baixa = 0;
        $detalhe->segmento_p->prazo_baixa = '   ';


        $detalhe->segmento_p->codigo_ocorrencia = $boleto['codigo_ocorrencia'];

        // SEGMENTO Q -------------------------------
        $detalhe->segmento_q->codigo_banco = $this->headerArquivo->codigo_banco;
        $detalhe->segmento_q->lote_servico = $this->headerLote->lote_servico;
        $detalhe->segmento_q->codigo_ocorrencia = $detalhe->segmento_p->codigo_ocorrencia;

        if (isset($boleto['sacado_cnpj'])) {
            $detalhe->segmento_q->sacado_codigo_inscricao = '2';
            $detalhe->segmento_q->sacado_numero_inscricao = $this->prepareText($boleto['sacado_cnpj'], '.-/');
            $detalhe->segmento_q->nome = $this->prepareText($boleto['sacado_razao_social']);
        }
        else {
            $detalhe->segmento_q->sacado_codigo_inscricao = '1';
            $detalhe->segmento_q->sacado_numero_inscricao = $this->prepareText($boleto['sacado_cpf'], '.-/');
            $detalhe->segmento_q->nome = $this->prepareText($boleto['sacado_nome']);
        }
        $detalhe->segmento_q->logradouro = $this->prepareText($boleto['sacado_logradouro']);
        $detalhe->segmento_q->bairro = $this->prepareText($boleto['sacado_bairro']);
        $detalhe->segmento_q->cep = str_replace(['-', '.'], '', $boleto['sacado_cep']);
        $detalhe->segmento_q->cidade = $this->prepareText($boleto['sacado_cidade']);
        $detalhe->segmento_q->estado = $boleto['sacado_uf'];

        // se o titulo for de terceiro, o sacador é o terceiro
        if ($boleto['terceiro']) {
            $detalhe->segmento_q->sacador_codigo_inscricao = $this->headerArquivo->codigo_inscricao;
            $detalhe->segmento_q->sacador_numero_inscricao = $this->headerArquivo->numero_inscricao;
            $detalhe->segmento_q->sacador_nome = $this->headerArquivo->nome_empresa;
        }
        else {
            $detalhe->segmento_q->sacador_codigo_inscricao = '0';
            $detalhe->segmento_q->sacador_numero_inscricao = '0';
            $detalhe->segmento_q->sacador_nome = '';
        }

        // SEGMENTO R -------------------------------
        $detalhe->segmento_r->codigo_banco = $detalhe->segmento_p->codigo_banco;
        $detalhe->segmento_r->lote_servico = $detalhe->segmento_p->lote_servico;
        $detalhe->segmento_r->codigo_ocorrencia = $detalhe->segmento_p->codigo_ocorrencia;

        if ($boleto['valor_multa'] > 0) {
            $detalhe->segmento_r->codigo_multa = 2;
            $detalhe->segmento_r->valor_multa = $boleto['valor_multa'];
            $detalhe->segmento_r->data_multa = $boleto['data_multa'];
        }
        else {
            $detalhe->segmento_r->codigo_multa = 0;
            $detalhe->segmento_r->valor_multa = 0;
            $detalhe->segmento_r->data_multa = 0;
        }

        $this->detalhes[] = $detalhe;
    }

    public function formatarNossoNumero($boleto)
    {
        if(!$boleto)
            return '';

        $nossoNumero = $boleto['nosso_numero'];

        if ($this->codigo_banco == \Cnab\Banco::BANCO_DO_BRASIL) {
            $codigo_convenio = $this->configuracao['codigo_convenio'];
            if(strlen($codigo_convenio) <= 4) {
                # Convênio de 4 digitos
                if(strlen($nossoNumero) > 7) {
                    throw new \InvalidArgumentException(
                        "Para número de convênio de 4 posições o nosso número deve ter no máximo 7 posições (sem o digito)"
                    );
                }
                $number = sprintf('%04d%07d', $codigo_convenio, $nossoNumero);
                return $number . $this->mod11($number);
            } elseif (strlen($codigo_convenio) <= 6) {
                # Convênio de 6 digitos
                if(strlen($nossoNumero) > 5) {
                    throw new \InvalidArgumentException(
                        "Para número de convênio de 6 posições o nosso número deve ter no máximo 5 posições (sem o digito)"
                    );
                }
                $number = sprintf('%06d%05d', $codigo_convenio, $nossoNumero);
                return $number . $this->mod11($number);
            } else {
                if(strlen($nossoNumero) > 10) {
                    throw new \InvalidArgumentException(
                        "Para número de convênio de 7 posições o nosso número deve ter no máximo 10 posições"
                    );
                }
                $number = sprintf('%07d%010d', $codigo_convenio, $nossoNumero);
                return $number;
            }
        }
        elseif ($this->codigo_banco == \Cnab\Banco::SICOOB) {
            $nossoNumero = str_pad($nossoNumero, 10, '0',
                    STR_PAD_LEFT) . '' . str_pad($boleto['parcela'], 2, '0', STR_PAD_LEFT) . '013     ';
        }

        return $nossoNumero;
    }

    public function listDetalhes()
    {
        return $this->detalhes;
    }

    private function prepareText($text, $remove = NULL)
    {
        $result = strtoupper($this->removeAccents(trim(html_entity_decode($text))));;
        if ($remove) {
            $result = str_replace(str_split($remove), '', $result);
        }

        return $result;
    }

    private function removeAccents($string)
    {
        return preg_replace(
            [
                '/\xc3[\x80-\x85]/',
                '/\xc3\x87/',
                '/\xc3[\x88-\x8b]/',
                '/\xc3[\x8c-\x8f]/',
                '/\xc3([\x92-\x96]|\x98)/',
                '/\xc3[\x99-\x9c]/',

                '/\xc3[\xa0-\xa5]/',
                '/\xc3\xa7/',
                '/\xc3[\xa8-\xab]/',
                '/\xc3[\xac-\xaf]/',
                '/\xc3([\xb2-\xb6]|\xb8)/',
                '/\xc3[\xb9-\xbc]/',
            ],
            str_split('ACEIOUaceiou', 1),
            $this->isUtf8($string) ? $string : utf8_encode($string)
        );
    }

    private function isUtf8($string)
    {
        return preg_match('%^(?:
                 [\x09\x0A\x0D\x20-\x7E]
                | [\xC2-\xDF][\x80-\xBF]
                | \xE0[\xA0-\xBF][\x80-\xBF]
                | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}
                | \xED[\x80-\x9F][\x80-\xBF]
                | \xF0[\x90-\xBF][\x80-\xBF]{2}
                | [\xF1-\xF3][\x80-\xBF]{3}
                | \xF4[\x80-\x8F][\x80-\xBF]{2}
                )*$%xs',
            $string
        );
    }

    public function getText()
    {
        $numero_sequencial_lote = 1;
        $qtde_registro_lote = 2; // header e trailer = 2
        $qtde_titulo_cobranca_simples = 0;
        $valor_total_titulo_simples = 0;

        // valida os dados
        if (!$this->headerArquivo->validate()) {
            throw new \InvalidArgumentException($this->headerArquivo->last_error);
        }
        if (!$this->headerLote->validate()) {
            throw new \InvalidArgumentException($this->headerArquivo->last_error);
        }

        $dados = $this->headerArquivo->getEncoded() . self::QUEBRA_LINHA;
        $dados .= $this->headerLote->getEncoded() . self::QUEBRA_LINHA;

        foreach ($this->detalhes as $detalhe) {
            $qtde_titulo_cobranca_simples++;
            $valor_total_titulo_simples += $detalhe->segmento_p->valor_titulo;
            foreach ($detalhe->listSegmento() as $segmento) {

                $qtde_registro_lote++;
                $segmento->numero_sequencial_lote = $numero_sequencial_lote++;
            }

            if (!$detalhe->validate()) {
                throw new \InvalidArgumentException($detalhe->last_error);
            }


            $dados .= $detalhe->getEncoded() . self::QUEBRA_LINHA;
        }
        $this->trailerLote->qtde_registro_lote = $qtde_registro_lote;

        // $this->trailerLote->qtde_titulo_cobranca_simples = $qtde_titulo_cobranca_simples;
        // $this->trailerLote->valor_total_titulo_simples = $valor_total_titulo_simples;
        if ($this->codigo_banco != \Cnab\Banco::BANCO_DO_BRASIL) {
            $this->trailerLote->qtde_titulo_cobranca_simples = 0;
            $this->trailerLote->valor_total_titulo_simples = 0;

            $this->trailerLote->qtde_titulo_cobranca_caucionada = 0;
            $this->trailerLote->valor_total_titulo_caucionada = 0;
            $this->trailerLote->qtde_titulo_cobranca_descontada = 0;
            $this->trailerLote->valor_total_titulo_descontada = 0;
        }

        if (!in_array($this->codigo_banco,[\Cnab\Banco::CEF, \Cnab\Banco::SICOOB, \Cnab\Banco::BANCO_DO_BRASIL])) {
            $this->trailerLote->qtde_titulo_cobranca_vinculada = $qtde_titulo_cobranca_simples;
            $this->trailerLote->valor_total_titulo_vinculada = $valor_total_titulo_simples;
        }
        elseif (in_array($this->codigo_banco,[\Cnab\Banco::SICOOB])) {
            $this->trailerLote->qtde_titulo_cobranca_vinculada = 0;
            $this->trailerLote->valor_total_titulo_vinculada = 0;
        }


        $this->trailerArquivo->qtde_lotes = 1;
        $this->trailerArquivo->qtde_registros = $this->trailerLote->qtde_registro_lote + 2;

        if ($this->codigo_banco == \Cnab\Banco::SICOOB) {
            $this->trailerArquivo->qtde_contas_conciliacao = 0;
        }

        if (!$this->trailerLote->validate()) {
            throw new \InvalidArgumentException($this->trailerLote->last_error);
        }


        if (!$this->trailerArquivo->validate()) {
            throw new \InvalidArgumentException($this->trailerArquivo->last_error);
        }

        $dados .= $this->trailerLote->getEncoded() . self::QUEBRA_LINHA;
        $dados .= $this->trailerArquivo->getEncoded() . self::QUEBRA_LINHA;

        return $dados;
    }

    public function countDetalhes()
    {
        return count($this->detalhes);
    }

    public function save($filename)
    {
        $text = $this->getText();

        file_put_contents($filename, $text);

        return $filename;
    }

    public function mod11($num, $base = 9, $r = 0)
    {
        $soma = 0;
        $fator = 2;
        /* Separacao dos numeros */
        for ($i = strlen($num); $i > 0; --$i) {
            // pega cada numero isoladamente
            $numeros[$i] = substr($num, $i - 1, 1);
            // Efetua multiplicacao do numero pelo falor
            $parcial[$i] = $numeros[$i] * $fator;
            // Soma dos digitos
            $soma += $parcial[$i];
            if ($fator == $base) { // restaura fator de multiplicacao para 2
                $fator = 1;
            }
            ++$fator;
        }
        /* Calculo do modulo 11 */
        if ($r == 0) {
            $soma *= 10;
            $digito = $soma % 11;
            if ($digito == 10) {
                $digito = 0;
            }
            return $digito;
        } elseif ($r == 1) {
            $resto = $soma % 11;
            return $resto;
        }
    }
}