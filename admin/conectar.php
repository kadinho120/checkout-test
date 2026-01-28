<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title>Conectar Facebook Ads</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding-top: 50px; }
        .btn-face {
            background-color: #1877F2; color: white; padding: 15px 30px;
            text-decoration: none; font-size: 16px; border-radius: 5px;
            cursor: pointer; border: none; font-weight: bold;
        }
        .btn-face:hover { background-color: #166fe5; }
        #status { margin-top: 20px; color: #555; }
    </style>
</head>
<body>

    <h1>Configuração de Surfscale</h1>
    <p>Clique abaixo para autorizar nossa ferramenta a ler e escalar seus anúncios.</p>
    
    <button onclick="loginFacebook()" class="btn-face">
        Conectar Conta de Anúncios
    </button>
    
    <div id="status"></div>

    <script>
      window.fbAsyncInit = function() {
        FB.init({
          appId      : 'SEU_APP_ID_AQUI', // <--- COLOQUE SEU APP ID AQUI
          cookie     : true,
          xfbml      : true,
          version    : 'v18.0'
        });
      };

      (function(d, s, id){
         var js, fjs = d.getElementsByTagName(s)[0];
         if (d.getElementById(id)) {return;}
         js = d.createElement(s); js.id = id;
         js.src = "https://connect.facebook.net/pt_BR/sdk.js";
         fjs.parentNode.insertBefore(js, fjs);
       }(document, 'script', 'facebook-jssdk'));

      function loginFacebook() {
        // Pedindo permissão para ler insights e gerenciar campanhas
        FB.login(function(response) {
            if (response.authResponse) {
                console.log('Bem vindo!  Buscando informações.... ');
                const accessToken = response.authResponse.accessToken;
                const userID = response.authResponse.userID;
                
                document.getElementById('status').innerHTML = 'Conectado! Salvando token...';
                
                // Enviar token para o PHP salvar
                salvarToken(accessToken, userID);
            } else {
                console.log('Usuário cancelou o login ou não autorizou totalmente.');
                document.getElementById('status').innerHTML = 'Erro: Você precisa aceitar as permissões.';
            }
        }, {scope: 'ads_read,ads_management,public_profile'}); 
      }

      function salvarToken(token, uid) {
          fetch('salvar_token_backend.php', {
              method: 'POST',
              headers: {'Content-Type': 'application/x-www-form-urlencoded'},
              body: 'token=' + token + '&uid=' + uid
          })
          .then(response => response.text())
          .then(data => {
              document.getElementById('status').innerHTML = '✅ Integração Concluída! Token Salvo.<br>Agora o robô já pode rodar.';
              // Opcional: Redirecionar para o dashboard
              // window.location.href = 'painel_surfscale.php';
          });
      }
    </script>
</body>
</html>
