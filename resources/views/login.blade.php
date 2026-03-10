<!DOCTYPE html>
<html>
<head>

<title>Login</title>

<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.3.2/dist/tailwind.min.css" rel="stylesheet">
<style>

body{
    font-family: Arial;
    background:#f4f6f9;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}

.login-box{
    background:white;
    padding:40px;
    width:350px;
    border-radius:10px;
    box-shadow:0 5px 20px rgba(0,0,0,0.1);
}

.login-box h2{
    text-align:center;
    margin-bottom:30px;
}

input{
    width:100%;
    padding:12px;
    margin-bottom:15px;
    border:1px solid #ddd;
    border-radius:6px;
}

button{
    width:100%;
    padding:12px;
    border:none;
    background:#4CAF50;
    color:white;
    font-size:16px;
    border-radius:6px;
    cursor:pointer;
}

button:hover{
    background:#45a049;
}

.error{
    color:red;
    margin-bottom:10px;
}

</style>

</head>

<body>

<div class="bg‑gray‑100 flex items‑center justify‑center h‑screen">
        <h2 class="text‑2xl font‑bold text‑center mb‑6">Sign In</h2>
        <p class="text‑red‑500 text‑center" id="error"></p>
        <input id="email" type="email" placeholder="Email"
            class="w‑full border‑2 border‑gray‑200 p‑2 rounded mt‑2">
        <input id="password" type="password" placeholder="Password"
            class="w‑full border‑2 border‑gray‑200 p‑2 rounded mt‑4">
        <button onclick="login()"
            class="w‑full bg‑blue‑600 text‑white p‑2 rounded mt‑6 hover:bg‑blue‑700">
            Login
        </button>
</div>

<script type="module">

import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";

import { 
getAuth, 
signInWithEmailAndPassword 
} from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

const firebaseConfig = {

    apiKey: "{{ env('FIREBASE_API_KEY') }}",
    authDomain: "{{ env('FIREBASE_AUTH_DOMAIN') }}",
    projectId: "{{ env('FIREBASE_PROJECT_ID') }}"

};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);

window.login = async function(){

    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;

    try{

        const userCredential = await signInWithEmailAndPassword(auth, email, password);

        const token = await userCredential.user.getIdToken();

        const response = await fetch("{{ url('/firebase-login') }}", {

            method:'POST',
            headers:{
                'Content-Type':'application/json',
                'X-CSRF-TOKEN':'{{ csrf_token() }}'
            },
            body: JSON.stringify({ token: token })

        });

        const data = await response.json();

        if(data.status === 'success'){

            // window.location = "{{ url('attendance/IT/2026-02-11/2026-02-25') }}";
            window.location = "{{ url('dashboard') }}";

        }else{

            document.getElementById('error').innerText = data.message;

        }

    }catch(e){

        document.getElementById('error').innerText = e.message;

    }

}

</script>

</body>
</html>