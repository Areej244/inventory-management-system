document.addEventListener("DOMContentLoaded", function () {
    var toggle = document.getElementById("navToggle");
    var links = document.getElementById("navLinks");

    if (toggle && links) {
        toggle.addEventListener("click", function () {
            var open = links.classList.toggle("open");
            toggle.setAttribute("aria-expanded", open ? "true" : "false");
        });
    }

    // Fade out success/error alerts after 5 seconds
    document.querySelectorAll(".alert-success").forEach(function (el) {
        setTimeout(function () {
            el.style.transition = "opacity 0.5s";
            el.style.opacity = "0";
            setTimeout(function () { el.remove(); }, 500);
        }, 5000);
    });
});