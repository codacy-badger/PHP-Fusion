function FullScreen() {
    if (!document.fullscreenElement &&
        !document.mozFullScreenElement &&
        !document.webkitFullscreenElement &&
        !document.msFullscreenElement) {
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen();
        } else if (document.documentElement.msRequestFullscreen) {
            document.documentElement.msRequestFullscreen();
        } else if (document.documentElement.mozRequestFullScreen) {
            document.documentElement.mozRequestFullScreen();
        } else if (document.documentElement.webkitRequestFullscreen) {
            document.documentElement.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        } else if (document.mozCancelFullScreen) {
            document.mozCancelFullScreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        }
    }
}

$(function () {
    var body = $("body"),
        sidebar = $(".sidebar");

    $('[data-toggle="tooltip"]').tooltip();
    $(".dropdown").on("show.bs.dropdown", function () {
        $(this).find(".dropdown-menu").first().stop(true, true).slideDown(300);
    });
    $(".dropdown").on("hide.bs.dropdown", function () {
        $(this).find(".dropdown-menu").first().stop(true, true).slideUp(200);
    });
    $("li.home-link a").text("").html('<i class="fa fa-home fa-lg"></i>');

    body.on("click", "[data-action]", function (e) {
        switch ($(this).data("action")) {
            case "hide-sidebar":
                e.preventDefault();
                if (body.hasClass("sidebar-toggled")) {
                    body.removeClass("sidebar-toggled");
                    $("#hide-sidebar .btn-toggle").removeClass("on");
                    Cookies.set("sidebar-toggled", 0);
                } else {
                    body.addClass("sidebar-toggled");
                    $("#hide-sidebar .btn-toggle").addClass("on");
                    Cookies.set("sidebar-toggled", 1);
                }
                break;
            case "sidebar-sm":
                e.preventDefault();
                if (body.hasClass("sidebar-sm")) {
                    body.removeClass("sidebar-sm");
                    $("#sidebar-sm .btn-toggle").removeClass("on");
                    Cookies.set("sidebar-sm", 0);
                } else {
                    body.addClass("sidebar-sm");
                    $("#sidebar-sm .btn-toggle").addClass("on");
                    Cookies.set("sidebar-sm", 1);
                }
                break;
            case "fixedsidebar":
                e.preventDefault();
                if (sidebar.hasClass("fixed")) {
                    sidebar.removeClass("fixed");
                    $("#fixedsidebar .btn-toggle").removeClass("on");
                    Cookies.set("fixedsidebar", 1);
                } else {
                    sidebar.addClass("fixed");
                    $("#fixedsidebar .btn-toggle").addClass("on");
                    Cookies.set("fixedsidebar", 0);
                }
                break;
            case "search-box":
                e.preventDefault();
                $(".sidebar-sm .search-box").toggle();
                break;
            case "togglemenu":
                e.preventDefault();
                body.toggleClass("sidebar-toggled");
                break;
            case "fixedmenu":
                e.preventDefault();
                if ($(".top-menu").hasClass("fixed") && $(".sidebar .header").hasClass("fixed")) {
                    $(".top-menu").removeClass("fixed");
                    $(".sidebar .header").removeClass("fixed");
                    $("#fixedmenu .btn-toggle").removeClass("on");
                    Cookies.set("fixedmenu", 1);
                } else {
                    $(".top-menu").addClass("fixed");
                    $(".sidebar .header").addClass("fixed");
                    $("#fixedmenu .btn-toggle").addClass("on");
                    Cookies.set("fixedmenu", 0);
                }
                break;
            case "messages":
                e.preventDefault();
                $(".messages-box").toggleClass("open");
                body.addClass("overlay-active");
                break;
            case "theme-settings":
                e.preventDefault();
                e.stopPropagation();
                $("#theme-settings").toggleClass("open");
                break;
            case "fixedfootererrors":
                e.preventDefault();
                if ($(".errors").hasClass("fixed")) {
                    $(".errors").removeClass("fixed");
                    $("#fixedfootererrors .btn-toggle").removeClass("on");
                    Cookies.set("fixedfootererrors", 1);
                } else {
                    $(".errors").addClass("fixed");
                    $("#fixedfootererrors .btn-toggle").addClass("on");
                    Cookies.set("fixedfootererrors", 0);
                }
                break;
            case "fullscreen":
                e.preventDefault();
                $("#fullscreen .btn-toggle").toggleClass("on");
                FullScreen();
                break;
            default:
                break;
        }
    });

    $(document).click(function () {
        $("#theme-settings").removeClass("open");
    });

    if (Cookies.get("sidebar-toggled") !== undefined) {
        if (Cookies.get("sidebar-toggled") === 1) {
            body.addClass("sidebar-toggled");
            $("#hide-sidebar .btn-toggle").addClass("on");
        }
    }

    if (Cookies.get("sidebar-sm") !== undefined) {
        if (Cookies.get("sidebar-sm") === 1) {
            body.addClass("sidebar-sm");
            $("#sidebar-sm .btn-toggle").addClass("on");
        }
    }

    if (Cookies.get("fixedsidebar") !== undefined) {
        if (Cookies.get("fixedsidebar") === 1) {
            sidebar.removeClass("fixed");
            $("#fixedsidebar .btn-toggle").removeClass("on");
        }
    }

    if (Cookies.get("fixedmenu") !== undefined) {
        if (Cookies.get("fixedmenu") === 1) {
            $(".top-menu").removeClass("fixed");
            $(".sidebar .header").removeClass("fixed");
            $("#fixedmenu .btn-toggle").removeClass("on");
        }
    }

    if (Cookies.get("fixedfootererrors") !== undefined) {
        if (Cookies.get("fixedfootererrors") === 1) {
            $(".errors").removeClass("fixed");
            $("#fixedfootererrors .btn-toggle").removeClass("on");
        }
    }

    $("#messages-box-close").on("click", function (e) {
        e.preventDefault();
        $(".messages-box").removeClass("open");
        body.removeClass("overlay-active");
    });

    var search_box = $("#search_box");

    search_box.focus(function () {
        $(".input-search-icon").addClass("focus");
    });

    search_box.blur(function () {
        $(".input-search-icon").removeClass("focus");
    });

    if (body.hasClass("sidebar-sm")) {
        $(".admin-vertical-link li.active .adl-link").addClass("collapsed");
        $(".admin-vertical-link li.active .collapse").removeClass("in");
    }

    $(".overlay").bind("click", function () {
        body.removeClass("overlay-active");
        $(".messages-box").removeClass("open");
    });

});

$(document).mouseup(function (e) {
    if ($(".sidebar-sm")[0]) {
        var ald_link = $(".admin-vertical-link .adl-link");
        if (!ald_link.is(e.target) && ald_link.has(e.target).length === 0) {
            $(".admin-vertical-link li .adl-link").addClass("collapsed");
            $(".admin-vertical-link li .collapse").removeClass("in");
        }
    }
});
