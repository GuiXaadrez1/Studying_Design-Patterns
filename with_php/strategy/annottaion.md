Desmistificando ponto a ponto (com os Princípios de Design)
1. "Separe o que varia daquilo que permanece igual"
O que permanece igual: O ciclo de vida do Pedido (valor, itens, finalização do fluxo).

O que varia: O algoritmo ou regra para processar o pagamento.

Com Strategy: As regras variáveis são extraídas para classes independentes (PagamentoPix, PagamentoCartaoCredito).

2. "Programe para interfaces, não para implementações"
O Pedido não guarda dependência de PagamentoPix nem de PagamentoCartaoCredito.

Ele se conecta exclusivamente com a interface EstrategiaPagamentoInterface.

3. "Prefira composição a herança" (DI / IoC)
Em vez de herdar comportamentos de uma superclasse, o Pedido recebe a estratégia por composição (setEstrategiaPagamento()) e delega a execução do cálculo.

4. "Princípio do Aberto/Fechado (OCP)"
Para aceitar Criptomoeda, você cria a classe PagamentoCrypto implements EstrategiaPagamentoInterface sem alterar nenhuma linha da classe Pedido existente.

5. "Projete sistemas com acoplamento fraco entre objetos que interagem"
O Pedido sabe apenas que a estratégia pode executar pagar(). Ele ignora os detalhes de como o cartão comunica com a credenciadora ou como o QR Code do Pix é gerado.