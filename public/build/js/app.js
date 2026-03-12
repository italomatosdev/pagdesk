/*
Template Name: webadmin - Admin & Dashboard Template
Author: Themesdesign
Version: 2.2.0
Website: https://Themesdesign.com/
Contact: Themesdesign@gmail.com
File: Main Js File
*/


(function () {

    'use strict';

    var language = localStorage.getItem('language');
    // Default Language
    var default_lang = 'en';

    // localStorage.setItem('language',default_lang);

    function initLanguage() {
        // Set new language
        if (language != null && language !== default_lang){
            setLanguage(language);
        }
        var languages = document.getElementsByClassName("language");
        languages && Array.from(languages).forEach(function (dropdown) {
            dropdown.addEventListener('click', function (event) {
                setLanguage(dropdown.getAttribute('data-lang'));
            });
        });
    }

    function setLanguage(lang) {
        document.querySelectorAll(".header-lang-img").forEach(function (el) {
            if (el) {
                switch (lang) {
                    case "eng":
                        el.src = "build/images/flags/us.jpg";
                        break;
                    case "sp":
                        el.src = "build/images/flags/spain.jpg";
                        break;
                    case "gr":
                        el.src = "build/images/flags/germany.jpg";
                        break;
                    case "it":
                        el.src = "build/images/flags/italy.jpg";
                        break;
                    case "ru":
                        el.src = "build/images/flags/russia.jpg";
                        break;
                    default:
                        el.src = "build/images/flags/us.jpg";
                        break;
                }
                localStorage.setItem('language', lang);
                language = localStorage.getItem('language');
                getLanguage();
            }
        });
    }

    // Multi language setting
    function getLanguage() {
        (language == null) ? setLanguage(default_lang) : false;
        var request = new XMLHttpRequest();
        // Instantiating the request object
        request.open("GET", 'build/lang/' + language + '.json');

        // Defining event listener for readystatechange event
        request.onreadystatechange = function () {
            // Check if the request is compete and was successful
            if (this.readyState === 4 && this.status === 200) {
                var data = JSON.parse(this.responseText);
                Object.keys(data).forEach(function (key) {
                    var elements = document.querySelectorAll("[data-key='" + key + "']");
                    elements.forEach(function (elem) {
                        elem.textContent = data[key];
                    });
                });
            }
        };
        // Sending the request to the server
        request.send();
    }


    function initMetisMenu() {
        // MetisMenu js
        var sideMenu = document.getElementById("side-menu");
        if (sideMenu && typeof MetisMenu !== 'undefined') {
            try {
                new MetisMenu('#side-menu');
            } catch (e) {
                console.error('Erro ao inicializar MetisMenu:', e);
            }
        }
    }

    function initCounterNumber() {
        var counter = document.querySelectorAll('.counter-value');
        var speed = 250; // The lower the slower
        counter && counter.forEach(function (counter_value) {
            function updateCount() {
                var target = +counter_value.getAttribute('data-target');
                var count = +counter_value.innerText;
                var inc = target / speed;
                if (inc < 1) {
                    inc = 1;
                }
                // Check if target is reached
                if (count < target) {
                    // Add inc to count and output in counter_value
                    counter_value.innerText = (count + inc).toFixed(0);
                    // Call function every ms
                    setTimeout(updateCount, 1);
                } else {
                    counter_value.innerText = target;
                }
            };
            updateCount();
        });
    }

    function initLeftMenuCollapse() {
        var currentSIdebarSize = document.body.getAttribute('data-sidebar-size');
        window.onload = function () {
            if (window.innerWidth >= 1024 && window.innerWidth <= 1366) {
                document.body.setAttribute('data-sidebar-size', 'sm');
                updateRadio('sidebar-size-small');
            }
        };
        var verticalButton = document.getElementsByClassName("vertical-menu-btn");
        for (var i = 0; i < verticalButton.length; i++) {
            (function (index) {
                verticalButton[index] && verticalButton[index].addEventListener('click', function (event) {
                    event.preventDefault();
                    document.body.classList.toggle('sidebar-enable');
                    if (window.innerWidth >= 992) {
                        if (currentSIdebarSize == null) {
                            (document.body.getAttribute('data-sidebar-size') == null || document.body.getAttribute('data-sidebar-size') == "lg") ? document.body.setAttribute('data-sidebar-size', 'sm') : document.body.setAttribute('data-sidebar-size', 'lg');
                        } else if (currentSIdebarSize == "md") {
                            (document.body.getAttribute('data-sidebar-size') == "md") ? document.body.setAttribute('data-sidebar-size', 'sm') : document.body.setAttribute('data-sidebar-size', 'md');
                        } else {
                            (document.body.getAttribute('data-sidebar-size') == "sm") ? document.body.setAttribute('data-sidebar-size', 'lg') : document.body.setAttribute('data-sidebar-size', 'sm');
                        }
                    } else {
                        initMenuItemScroll();
                    }
                });
            })(i);
        }
    }

    function initActiveMenu() {

        setTimeout(function () {
            // === following js will activate the menu in left side bar based on url ====
            var menuItems = document.querySelectorAll("#sidebar-menu a");
            var pageUrl = window.location.href.split(/[?#]/)[0];

            menuItems && menuItems.forEach(function (item) {
                var itemUrl = item.href.split(/[?#]/)[0];
                
                // Comparação exata ou rota filha
                if (itemUrl == pageUrl || (itemUrl !== pageUrl && pageUrl.indexOf(itemUrl + '/') === 0)) {
                    item.classList.add("active");
                    var parent = item.parentElement;
                    if (parent && parent.id !== "side-menu") {
                        parent.classList.add("mm-active");
                        var parent2 = parent.parentElement; // ul .
                        if (parent2 && parent2.id !== "side-menu") {
                            parent2.classList.add("mm-show"); // ul tag
                            if (parent2.classList.contains('mm-collapsing')) {
                                console.log('has mm-collapsing');
                            }
                            var parent3 = parent2.parentElement; // li tag
                            if (parent3 && parent3.id !== "side-menu") {
                                parent3.classList.add("mm-active"); // li
                                var parent4 = parent3.parentElement; // ul
                                if (parent4 && parent4.id !== "side-menu") {
                                    parent4.classList.add("mm-show"); // ul
                                    var parent5 = parent4.parentElement;
                                    if (parent5 && parent5.id !== "side-menu") {
                                        parent5.classList.add("mm-active"); // li
                                    }
                                }
                            }
                        }
                    }
                }
            });
        }, 0);
    }

    function initMenuItemScroll() {
        setTimeout(function () {
            var sidebarMenu = document.getElementById("side-menu");
            if (sidebarMenu) {
                var activeMenu = sidebarMenu.querySelector(".mm-active .active");
                var offset = (activeMenu) ? activeMenu.offsetTop : 0;
                if (offset > 300) {
                    offset = offset - 100;
                    var verticalMenu = (document.getElementsByClassName("vertical-menu")) ? document.getElementsByClassName("vertical-menu")[0] : '';
                    if (verticalMenu && verticalMenu.querySelector(".simplebar-content-wrapper")) {
                        setTimeout(function () {
                            verticalMenu.querySelector(".simplebar-content-wrapper").scrollTop = offset;
                        }, 0);
                    }
                }
            }
        }, 0);
    }

    function initHoriMenuActive() {

        var navlist = document.querySelectorAll(".navbar-nav a");
        navlist && navlist.forEach(function (item) {
            var pageUrl = window.location.href.split(/[?#]/)[0];
            if (item.href == pageUrl) {
                item.classList.add("active");
                var parent = item.parentElement;
                if (parent) {
                    parent.classList.add("active"); // li
                    var parent2 = parent.parentElement;
                    parent2.classList.add("active"); // li
                    var parent3 = parent2.parentElement;
                    if (parent3) {
                        parent3.classList.add("active"); // li
                        var parent4 = parent3.parentElement;
                        if (parent4.closest("li"))
                            parent4.closest("li").classList.add("active");
                        if (parent4) {
                            parent4.classList.add("active"); // li
                            var parent5 = parent4.parentElement;
                            if (parent5) {
                                parent5.classList.add("active"); // li
                                var parent6 = parent5.parentElement;
                                if (parent6) {
                                    parent6.classList.add("active"); // li
                                }
                            }
                        }
                    }
                }
            }
        });
    }

    function initFullScreen() {
        var fullscreenBtn = document.querySelector('[data-toggle="fullscreen"]');
        fullscreenBtn && fullscreenBtn.addEventListener('click', function (e) {
            e.preventDefault();
            document.body.classList.toggle('fullscreen-enable');
            if (!document.fullscreenElement && /* alternative standard method */ !document.mozFullScreenElement && !document.webkitFullscreenElement) { // current working methods
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                } else if (document.documentElement.mozRequestFullScreen) {
                    document.documentElement.mozRequestFullScreen();
                } else if (document.documentElement.webkitRequestFullscreen) {
                    document.documentElement.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
                }
            } else {
                if (document.cancelFullScreen) {
                    document.cancelFullScreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.webkitCancelFullScreen) {
                    document.webkitCancelFullScreen();
                }
            }
        });

        document.addEventListener('fullscreenchange', exitHandler);
        document.addEventListener("webkitfullscreenchange", exitHandler);
        document.addEventListener("mozfullscreenchange", exitHandler);

        function exitHandler() {
            if (!document.webkitIsFullScreen && !document.mozFullScreen && !document.msFullscreenElement) {
                document.body.classList.remove('fullscreen-enable');
            }
        }
    }

    function initDropdownMenu() {
        if (document.getElementById("topnav-menu-content")) {
            var elements = document.getElementById("topnav-menu-content").getElementsByTagName("a");
            for (var i = 0, len = elements.length; i < len; i++) {
                elements[i].onclick = function (elem) {
                    if (elem.target.getAttribute("href") === "#") {
                        elem.target.parentElement.classList.toggle("active");
                        elem.target.nextElementSibling.classList.toggle("show");
                    }
                };
            }
            window.addEventListener("resize", updateMenu);
        }
    }

    function updateMenu() {
        var elements = document.getElementById("topnav-menu-content").getElementsByTagName("a");
        for (var i = 0, len = elements.length; i < len; i++) {
            if (elements[i].parentElement.getAttribute("class") === "nav-item dropdown active") {
                elements[i].parentElement.classList.remove("active");
                elements[i].nextElementSibling.classList.remove("show");
            }
        }
    }

    function initComponents() {
        // tooltip
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // popover
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        // toast
        var toastElList = [].slice.call(document.querySelectorAll('.toast'));
        var toastList = toastElList.map(function (toastEl) {
            return new bootstrap.Toast(toastEl);
        });
    }

    function fadeOutEffect(elem, delay) {
        var fadeTarget = document.getElementById(elem);
        fadeTarget.style.display = 'block';
        var fadeEffect = setInterval(function () {
            if (!fadeTarget.style.opacity) {
                fadeTarget.style.opacity = 1;
            }
            if (fadeTarget.style.opacity > 0) {
                fadeTarget.style.opacity -= 0.2;
            } else {
                clearInterval(fadeEffect);
                fadeTarget.style.display = 'none';
            }
        }, 200);
    }

    function initPreloader() {
        window.onload = function () {
            if (document.getElementById("preloader")) {
                fadeOutEffect('pre-status', 300);
                fadeOutEffect('preloader', 400);
            }
        };
    }

    function initSettings() {
        // Eva Icons
        eva.replace();

        if (window.sessionStorage) {
            var alreadyVisited = sessionStorage.getItem("is_visited");
            if (!alreadyVisited) {
                sessionStorage.setItem("is_visited", "layout-direction-ltr");
            } else {
                var value = document.querySelector("#" + alreadyVisited);
                if (value !== null) {
                    value.checked = true;
                    changeDirection(alreadyVisited);
                }
            }
        }

        var body = document.getElementsByTagName("body")[0];
        if (body.hasAttribute("data-layout") && body.getAttribute("data-layout") == "horizontal") {
            updateRadio("layout-horizontal");
            document.getElementById("sidebar-setting").style.display = "none";
            document.getElementsByClassName("vertical-menu")[0].style.display = "none";
            document.getElementsByClassName("ishorizontal-topbar")[0].style.display = "block";
            document.getElementsByClassName("isvertical-topbar")[0].style.display = "none";
            document.getElementsByClassName("topnav")[0].style.display = "block";
            document.getElementsByClassName("footer")[0].style.display = "block";
            if (body.hasAttribute("data-topbar") == "light") {
                updateRadio('topbar-color-light');
                document.body.setAttribute("data-topbar", "light");
            } else if (body.hasAttribute("data-topbar") == "dark") {
                updateRadio('topbar-color-dark');
                document.body.setAttribute("data-topbar", "dark");
            }
            document.body.removeAttribute("data-sidebar");
        } else {
            // document.body.setAttribute("data-sidebar", "light");
            // document.getElementsByClassName("isvertical-topbar")[0].style.display = "block";
            document.getElementsByClassName("isvertical-topbar")[0] ? document.getElementsByClassName("isvertical-topbar")[0].style.display = "block" : "";
            updateRadio("layout-vertical");
            // document.getElementsByClassName("topnav")[0].style.display = "none";
            document.getElementsByClassName("topnav")[0] ?  document.getElementsByClassName("topnav")[0].style.display = "none" : "";
            // document.getElementsByClassName("vertical-menu")[0].style.display =
            //     "block";
            // document.getElementsByClassName("ishorizontal-topbar")[0].style.display = "none";
            document.getElementsByClassName("ishorizontal-topbar")[0] ?  document.getElementsByClassName("ishorizontal-topbar")[0].style.display = "none" : "";
        }
    }

    function changeDirection(id) {
        if (document.getElementById("layout-direction-ltr").checked == true && id === "layout-direction-ltr") {
            document.getElementsByTagName("html")[0].removeAttribute("dir");
            document.getElementById("layout-direction-rtl").checked = false;
            document.getElementById('bootstrap-style').setAttribute('href', '/build/css/bootstrap.min.css');
            document.getElementById('app-style').setAttribute('href', '/build/css/app.min.css');
            sessionStorage.setItem("is_visited", "layout-direction-ltr");
        } else if (document.getElementById("layout-direction-rtl").checked == true && id === "layout-direction-rtl") {
            document.getElementById("layout-direction-ltr").checked = false;
            document.getElementById('app-style').setAttribute('href', '/build/css/app.min.rtl.css');
            document.getElementsByTagName("html")[0].setAttribute("dir", "rtl");
            sessionStorage.setItem("is_visited", "layout-direction-rtl");
        }
    }


    function updateRadio(radioId) {
        if (document.getElementById(radioId))
            document.getElementById(radioId).checked = true;
    }

    function layoutSetting() {
        var body = document.body;
        var rightSidebar = document.getElementsByClassName("right-bar-toggle");

        Array.from(rightSidebar).forEach(function (element) {
            element.addEventListener('click', function (e) {
                body.classList.toggle('right-bar-enabled');
            });
        });

        body.addEventListener('click', function (e) {
            if (e.target.parentElement.classList.contains("right-bar-toggle-close")) {
                document.body.classList.remove('right-bar-enabled');
                return;
            } else if (e.target.closest('.right-bar-toggle, .right-bar')) {
                return;
            }
            document.body.classList.remove('right-bar-enabled');
            return;
        });
        var body = document.getElementsByTagName("body")[0];
        if (body.hasAttribute("data-layout") && body.getAttribute("data-layout") == "horizontal") {
            updateRadio('layout-horizontal');
            if (body.hasAttribute("data-topbar") == "light") {
                updateRadio('topbar-color-light');
                document.body.setAttribute("data-topbar", "light");
            } else if (body.hasAttribute("data-topbar") == "dark") {
                updateRadio('topbar-color-dark');
                document.body.setAttribute("data-topbar", "dark");
            }

            document.getElementById('sidebar-setting').style.display = "none";
            document.body.removeAttribute("data-sidebar");

            //    (body.hasAttribute("data-topbar") ? body.removeAttribute("data-topbar") : body.setAttribute("data-topbar", ""));
        } else {
            updateRadio('layout-vertical');
            // document.getElementById('sidebar-setting').style.display = "block";
            document.getElementById('sidebar-setting') ? document.getElementById('sidebar-setting').style.display = "block" : "";
        }
        (body.hasAttribute("data-bs-theme") && body.getAttribute("data-bs-theme") == "dark") ? updateRadio('layout-mode-dark') : updateRadio('layout-mode-light');
        (body.hasAttribute("data-layout-size") && body.getAttribute("data-layout-size") == "boxed") ? updateRadio('layout-width-boxed') : updateRadio('layout-width-fluid');
        (body.hasAttribute("data-layout-scrollable") && body.getAttribute("data-layout-scrollable") == "true") ? updateRadio('layout-position-scrollable') : updateRadio('layout-position-fixed');
        (body.hasAttribute("data-topbar") && body.getAttribute("data-topbar") == "dark") ? updateRadio('topbar-color-dark') : updateRadio('topbar-color-light');
        (body.hasAttribute("data-sidebar-size") && body.getAttribute("data-sidebar-size") == "sm") ? updateRadio('sidebar-size-small') : (body.hasAttribute("data-sidebar-size") && body.getAttribute("data-sidebar-size") == "md") ? updateRadio('sidebar-size-compact') : updateRadio('sidebar-size-default');
        // Sidebar color: se não houver atributo, definir "brand" como padrão
        if (!body.hasAttribute("data-sidebar")) {
            body.setAttribute("data-sidebar", "brand");
            updateRadio('sidebar-color-brand');
        } else {
            (body.getAttribute("data-sidebar") == "brand") ? updateRadio('sidebar-color-brand') : (body.getAttribute("data-sidebar") == "dark") ? updateRadio('sidebar-color-dark') : updateRadio('sidebar-color-light');
        }
        (document.getElementsByTagName("html")[0].hasAttribute("dir") && document.getElementsByTagName("html")[0].getAttribute("dir") == "rtl") ? updateRadio('layout-direction-rtl') : updateRadio('layout-direction-ltr');

        // on layout change
        document.querySelectorAll("input[name='layout'").forEach(function (input) {
            input.addEventListener("change", function (e) {
                if (e && e.target && e.target.value == "vertical") {
                    updateRadio("layout-vertical");
                    updateRadio('topbar-color-light');
                    document.body.setAttribute("data-layout", "vertical");
                    document.body.setAttribute("data-topbar", "light");
                    document.getElementsByClassName("isvertical-topbar")[0].style.display = "block";
                    document.getElementsByClassName("ishorizontal-topbar")[0].style.display = "none";
                    document.getElementsByClassName("vertical-menu")[0].style.display = "block";
                    document.getElementById("sidebar-setting").style.display = "block";
                    document.getElementsByClassName("topnav")[0].style.display = "none";
                    if (window.innerWidth <= 992) {
                        document.getElementsByClassName("vertical-menu")[0].removeAttribute('style');
                    }
                    document.getElementsByClassName("footer")[0].style.display = "none";

                } else {
                    updateRadio("layout-horizontal");
                    updateRadio('topbar-color-light');
                    document.body.setAttribute('data-topbar', 'light');
                    document.body.setAttribute("data-layout", "horizontal");
                    document.body.removeAttribute("data-sidebar");
                    document.getElementById("sidebar-setting").style.display = "none";
                    document.getElementsByClassName("vertical-menu")[0].style.display =
                        "none";
                    document.getElementsByClassName("ishorizontal-topbar")[0].style.display = "block";

                    document.getElementsByClassName("isvertical-topbar")[0].style.display = "none";

                    document.getElementsByClassName("topnav")[0].style.display = "block";
                    document.getElementsByClassName("footer")[0].style.display = "block";
                }
            });
        });

        // on layout light mode change
        document.querySelectorAll("input[name='layout-mode']").forEach(function (input) {
            input.addEventListener('change', function (e) {
                if (e && e.target && e.target.value) {
                    if (e.target.value == "light") {
                        document.body.setAttribute('data-bs-theme', 'light');
                        document.body.setAttribute('data-topbar', 'light');
                        document.body.setAttribute('data-sidebar', 'light');
                        (body.hasAttribute("data-layout") && body.getAttribute("data-layout") == "horizontal") ? '' : document.body.setAttribute('data-sidebar', 'light');
                        updateRadio('topbar-color-light');
                        updateRadio('sidebar-color-light');
                    } else if (e.target.value == "dark") {
                        document.body.setAttribute('data-bs-theme', 'dark');
                        document.body.setAttribute('data-topbar', 'dark');
                        document.body.setAttribute('data-sidebar', 'dark');
                        (body.hasAttribute("data-layout") && body.getAttribute("data-layout") == "horizontal") ? '' : document.body.setAttribute('data-sidebar', 'dark');
                        updateRadio('topbar-color-dark');
                        // updateRadio('sidebar-color-dark');
                    } else if (e.target.value == "bordered") {
                        document.body.setAttribute('data-bs-theme', 'bordered');
                    }
                }
            });
        });

        // on layout direction change
        document.querySelectorAll("input[name='layout-direction']").forEach(function (input) {
            input.addEventListener('change', function (e) {
                if (e && e.target && e.target.value) {
                    if (e.target.value == "ltr") {
                        document.getElementsByTagName("html")[0].removeAttribute("dir");
                        document.getElementById('bootstrap-style').setAttribute('href', '/build/css/bootstrap.min.css');
                        document.getElementById('app-style').setAttribute('href', '/build/css/app.min.css');
                        sessionStorage.setItem("is_visited", "layout-direction-ltr");
                    } else {
                        document.getElementById('app-style').setAttribute('href', '/build/css/app.min.rtl.css');
                        document.getElementsByTagName("html")[0].setAttribute("dir", "rtl");
                        sessionStorage.setItem("is_visited", "layout-direction-rtl");
                    }
                }
            });
        });

        // on sidebar color change
        document.querySelectorAll("input[name='sidebar-color']").forEach(function (input) {
            input.addEventListener('change', function (e) {
                if (e && e.target && e.target.value) {
                    var sidebarColor = e.target.value;
                    if (body.hasAttribute("data-layout") && body.getAttribute("data-layout") == "horizontal") {
                        // No horizontal layout, sidebar color não se aplica
                        return;
                    }
                    document.body.setAttribute('data-sidebar', sidebarColor);
                    updateRadio('sidebar-color-' + sidebarColor);
                }
            });
        });
    }

    function initCheckAll() {
        var checkAll = document.getElementById("checkAll");
        if (checkAll) {
            checkAll.onclick = function () {
                var checkboxes = document.querySelectorAll('.table-check input[type="checkbox"]');
                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = this.checked;
                }
            };
        }
    }

    function init() {
        initPreloader();
        initSettings();
        initCounterNumber();
        initLeftMenuCollapse();
        initHoriMenuActive();
        initFullScreen();
        initDropdownMenu();
        initComponents();
        initLanguage();
        layoutSetting();
        initCheckAll();
        
        // Inicializar MetisMenu primeiro, depois as funções que dependem dele
        setTimeout(function() {
            initMetisMenu();
            // Aguardar MetisMenu inicializar antes de ativar menu
            setTimeout(function() {
                initActiveMenu();
                initMenuItemScroll();
            }, 50);
        }, 100);
        
        // Inicializar notificações após um delay
        setTimeout(function() {
            initNotificacoes();
        }, 500);
    }

    // Aguardar DOM estar pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM já está pronto, mas aguardar um pouco para garantir que tudo está carregado
        setTimeout(init, 50);
    }

    // Sistema de Notificações
    let notificacoesInicializadas = false;
    
    function initNotificacoes() {
        // Evitar múltiplas inicializações
        if (notificacoesInicializadas) {
            return;
        }
        
        const badge = document.getElementById('notificacao-badge');
        const lista = document.getElementById('notificacoes-lista');
        const marcarTodasLidas = document.getElementById('marcar-todas-lidas');
        
        if (!badge || !lista) {
            return;
        }
        
        notificacoesInicializadas = true;

        // Carregar notificações
        function carregarNotificacoes() {
            // Sempre mostrar loading ao iniciar
            lista.innerHTML = '<div class="text-center p-3"><div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>';

            fetch('/api/notificacoes?limit=10', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    atualizarBadge(data.nao_lidas || 0);
                    renderizarNotificacoes(data.notificacoes || []);
                })
                .catch(error => {
                    // Exibir mensagem de erro
                    lista.innerHTML = `
                        <div class="text-center p-4">
                            <div class="mb-3">
                                <i class="bx bx-error-circle font-size-24 text-danger"></i>
                            </div>
                            <p class="text-muted mb-0">Erro ao carregar notificações</p>
                            <small class="text-muted">Tente recarregar a página</small>
                        </div>
                    `;
                });
        }

        // Atualizar badge
        function atualizarBadge(naoLidas) {
            if (naoLidas > 0) {
                badge.textContent = naoLidas > 99 ? '99+' : naoLidas;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }

        // Renderizar notificações
        function renderizarNotificacoes(notificacoes) {
            if (notificacoes.length === 0) {
                lista.innerHTML = `
                    <div class="text-center p-4">
                        <div class="mb-3">
                            <i class="bx bx-bell-off font-size-24 text-muted"></i>
                        </div>
                        <p class="text-muted mb-0">Nenhuma notificação</p>
                        <small class="text-muted">Você está em dia!</small>
                    </div>
                `;
                return;
            }

            let html = '';
            notificacoes.forEach(notif => {
                const cor = notif.cor || 'primary';
                const icone = notif.icone || 'bx-bell';
                const classeLida = notif.lida ? '' : 'fw-semibold';
                const url = notif.url || 'javascript:void(0);';
                
                html += `
                    <a href="${url}" class="text-reset notification-item ${classeLida}" data-id="${notif.id}" ${notif.lida ? '' : 'onclick="marcarNotificacaoComoLida(event, ' + notif.id + ')"'}>
                        <div class="d-flex">
                            <div class="flex-shrink-0 avatar-sm me-3">
                                <span class="avatar-title bg-${cor}-subtle rounded-circle font-size-18">
                                    <i class="bx ${icone} text-${cor}"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <p class="text-muted font-size-13 mb-0 float-end">${notif.tempo_relativo}</p>
                                <h6 class="mb-1 ${classeLida}">${notif.titulo}</h6>
                                <div>
                                    <p class="mb-0">${notif.mensagem}</p>
                                </div>
                            </div>
                        </div>
                    </a>
                `;
            });

            lista.innerHTML = html;
        }

        // Marcar notificação como lida
        window.marcarNotificacaoComoLida = function(event, id) {
            event.preventDefault();
            const url = event.currentTarget.getAttribute('href');
            
            fetch(`/api/notificacoes/${id}/marcar-lida`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    atualizarBadge(data.nao_lidas);
                    // Remover classe de não lida
                    const item = document.querySelector(`[data-id="${id}"]`);
                    if (item) {
                        item.classList.remove('fw-semibold');
                        item.removeAttribute('onclick');
                    }
                    // Redirecionar se houver URL
                    if (url && url !== 'javascript:void(0);') {
                        window.location.href = url;
                    }
                }
            })
            .catch(error => {
                // Redirecionar mesmo se houver erro
                if (url && url !== 'javascript:void(0);') {
                    window.location.href = url;
                }
            });
        };

        // Marcar todas como lidas
        if (marcarTodasLidas && !marcarTodasLidas.hasAttribute('data-listener')) {
            marcarTodasLidas.setAttribute('data-listener', 'true');
            marcarTodasLidas.addEventListener('click', function(e) {
                e.preventDefault();
                fetch('/api/notificacoes/marcar-todas-lidas', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        atualizarBadge(0);
                        carregarNotificacoes();
                    }
                })
                .catch(error => {
                    // Silenciar erro
                });
            });
        }

        // Carregar notificações ao abrir dropdown
        const dropdown = document.getElementById('page-header-notifications-dropdown-v');
        
        if (dropdown && !dropdown.hasAttribute('data-notificacoes-listener')) {
            dropdown.setAttribute('data-notificacoes-listener', 'true');
            dropdown.addEventListener('shown.bs.dropdown', function() {
                // Sempre carregar quando o dropdown abrir
                carregarNotificacoes();
            });
        }

        // Função para atualizar badge
        function atualizarBadgeContador() {
            fetch('/api/notificacoes/contar', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                atualizarBadge(data.nao_lidas || 0);
            })
            .catch(error => {
                // Silenciar erro
            });
        }

        // Atualizar badge na inicialização e depois a cada 60 segundos
        // Usar uma flag para evitar múltiplos intervals
        if (!window.notificacoesIntervalId) {
            atualizarBadgeContador();
            window.notificacoesIntervalId = setInterval(atualizarBadgeContador, 60000);
        }
    }


})();
