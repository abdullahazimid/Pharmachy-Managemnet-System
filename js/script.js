document.getElementById("loginForm").addEventListener("submit", function (event) {

    event.preventDefault();

    let username = document.getElementById("username").value.trim();

    let password = document.getElementById("password").value.trim();

    let message = document.getElementById("message");

    if (username === "" || password === "") {

        message.style.color = "red";

        message.innerHTML = "Please enter username and password.";

        return;

    }

    message.style.color = "green";

    message.innerHTML = "Login Successful";

    setTimeout(function () {

        window.location.href = "html/dashboard.html";

    }, 700);

});