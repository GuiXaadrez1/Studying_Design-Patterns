# Desmistificando ponto a ponto (com os Princípios de Design)

1. "Separe o que varia daquilo que permanece igual"
No problema: A forma de capturar as medições de clima permanece no objeto WeatherData. Já os tipos de exibição, alertas, estatísticas e telas variam constantemente (novos painéis entram, antigos saem).

Com Observer: O núcleo de estado (EstacaoMeteorologica) fica isolado do que varia. As reações aos eventos do clima variam e são isoladas dentro de cada classe Observer separada.

2. "Programe para interfaces, não para implementações"
O sujeito EstacaoMeteorologica nunca interage com classes concretas como PainelCondicoesAtuais ou LoggerClimatico.

Ele interage exclusivamente com a interface SplObserver. Isso garante que qualquer nova classe que assine o contrato possa ser notificada sem ajustes no sujeito.

3. "Prefira composição a herança" (DI / IoC)
A relação de notificação é montada via composição: o sujeito guarda uma lista (SplObjectStorage) de objetos observadores injetados dinamicamente via método attach().

Não há herança rígida de código aqui. A execução dinâmica ocorre porque o sujeito delega a atualização para a coleção de objetos compostos nele em tempo de execução.

4. "Princípio do Aberto/Fechado (OCP)"
A classe EstacaoMeteorologica está fechada para modificação: você nunca precisará alterar o código do método notify() ou setMedicoes() para suportar novas telas.

O sistema está aberto para extensão: para criar um novo painel (ex: PainelPrevisaoTempo), basta criar a nova classe implementando SplObserver e registrá-la via attach().

5. "Projete sistemas com acoplamento fraco entre objetos que interagem"
Este é o princípio central do Observer!

O que o Sujeito sabe sobre o Observador? Sabe apenas que ele implementa a interface SplObserver. Ele não sabe o que o observador faz com o dado, qual é a classe concreta dele ou como ele renderiza a tela.

O que o Observador sabe sobre o Sujeito? Sabe apenas que ele implementa SplSubject e fornece métodos para consultar o estado.

Resultado: Sujeito e Observadores podem ser reutilizados, modificados ou testados isoladamente sem afetar uns aos outros.

Resumo da Abordagem de Design no Livro
A implementação direta chamando métodos internos das telas foi descartada porque criava um acoplamento forte e violava o OCP. A adoção de interfaces (SplSubject e SplObserver) estabeleceu o acoplamento fraco, permitindo estender o sistema via composição em tempo de execução.