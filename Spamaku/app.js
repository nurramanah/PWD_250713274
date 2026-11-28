/* ================================================================
   APP.JS — semua JavaScript Spamaku! ada di sini.
   Isinya per bagian:
     1. Splash screen "MADE BY"
     2. Hal-hal umum (menu HP, tema, animasi, konfirmasi, dll)
     3. Tombol keranjang ♥ tanpa reload
     4. Cek username/email waktu ngetik
     5. Geolocation (event terdekat)
     6. Form event (online/offline, poster)
     7. Halaman utama index.xhtml (ambil data dari api.php)
   Tiap bagian ngecek dulu elemennya ada atau nggak, jadi aman
   dipanggil di halaman mana aja. JS yang tau diri.
   ================================================================ */
(function () {
  "use strict";

  var akar = document.documentElement;


  /* ==============================================================
     1. SPLASH SCREEN
     Animasinya diatur di style.css, di sini cuma ngatur kapan mulai
     dan kapan dihapus. Tampil sekali per sesi biar nggak nyebelin.
     ============================================================== */
  (function () {
    var splash = document.getElementById("splash");
    if (!splash || akar.classList.contains("no-splash")) return;

    // user yang minta animasi dikurangin? langsung skip, hargai pilihannya
    if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      finish();
      return;
    }

    akar.classList.add("splash-active");
    var logo = splash.querySelector(".splash__logo img");
    var isMulai = false;

    function play() {
      if (isMulai) return;
      isMulai = true;
      splash.classList.add("is-playing");
    }

    // tunggu logo kelar di-load dulu baru animasi jalan (maks 1,5 detik, abis itu nekat aja)
    if (logo && logo.decode) logo.decode().then(play, play);
    else play();
    setTimeout(play, 1500);

    splash.addEventListener("animationend", function (event) {
      if (event.target === splash && event.animationName === "splash-fade-out") finish();
    });

    function skip() { splash.classList.add("is-leaving"); }   // nggak sabaran? klik aja
    splash.addEventListener("click", skip);
    document.addEventListener("keydown", skip, { once: true });

    function finish() {
      try { sessionStorage.setItem("splashSpamakuTampil", "1"); } catch (error) {}
      akar.classList.remove("splash-active");
      document.removeEventListener("keydown", skip);
      if (splash.parentNode) splash.parentNode.removeChild(splash);
    }
  })();


  /* ==============================================================
     2. HAL-HAL UMUM
     ============================================================== */

  // menu hamburger di HP 🍔
  var tombolMenu = document.querySelector("[data-nav-toggle]");
  var menu = document.querySelector("[data-nav]");
  if (tombolMenu && menu) {
    tombolMenu.addEventListener("click", function () {
      var isTerbuka = menu.classList.toggle("open");
      tombolMenu.setAttribute("aria-expanded", isTerbuka ? "true" : "false");
    });
  }

  // klik di luar menu profil = menu ditutup
  document.addEventListener("click", function (event) {
    document.querySelectorAll("details.usermenu[open]").forEach(function (menuPengguna) {
      if (!menuPengguna.contains(event.target)) menuPengguna.removeAttribute("open");
    });
  });

  // ganti tema gelap ↔ terang. disimpen di localStorage biar besok masih inget
  document.querySelectorAll("[data-theme-toggle]").forEach(function (tombol) {
    tombol.addEventListener("click", function () {
      var temaBaru = akar.getAttribute("data-theme") === "light" ? "dark" : "light";
      akar.setAttribute("data-theme", temaBaru);
      try { localStorage.setItem("temaSpamaku", temaBaru); } catch (error) {}
    });
  });

  // animasi muncul pas elemen kelihatan di layar. pakai IntersectionObserver, bukan event scroll,
  // soalnya event scroll itu rakus CPU kayak Chrome makan RAM
  function pasangAnimasi(akarCari) {
    var elemen = (akarCari || document).querySelectorAll(".reveal:not(.is-visible), .progress__fill.pending");
    if (!("IntersectionObserver" in window)) {
      elemen.forEach(function (el) { el.classList.add("is-visible"); el.classList.remove("pending"); });
      return;
    }
    var pengamat = new IntersectionObserver(function (daftar) {
      daftar.forEach(function (entri) {
        if (!entri.isIntersecting) return;
        entri.target.classList.add("is-visible");
        entri.target.classList.remove("pending");
        pengamat.unobserve(entri.target);
      });
    }, { threshold: 0.12 });
    elemen.forEach(function (el) { pengamat.observe(el); });
  }
  pasangAnimasi();

  // form dengan data-confirm nanya dulu sebelum dikirim. "yakin? beneran? serius?"
  document.addEventListener("submit", function (event) {
    var pertanyaan = event.target.getAttribute("data-confirm");
    if (pertanyaan && !window.confirm(pertanyaan)) event.preventDefault();
  });

  // tombol LIHAT/SEMBUNYI password. buat yang suka typo
  document.querySelectorAll("[data-pw-toggle]").forEach(function (tombol) {
    tombol.addEventListener("click", function () {
      var input = document.getElementById(tombol.getAttribute("data-pw-toggle"));
      var isTampil = input.type === "password";
      input.type = isTampil ? "text" : "password";
      tombol.textContent = isTampil ? "SEMBUNYI" : "LIHAT";
    });
  });

  // pratinjau gambar sebelum diupload (foto profil & poster)
  document.querySelectorAll("input[type=file][data-preview]").forEach(function (input) {
    input.addEventListener("change", function () {
      var gambar = document.getElementById(input.getAttribute("data-preview"));
      var berkas = input.files && input.files[0];
      if (!gambar || !berkas || berkas.type.indexOf("image/") !== 0) return;
      var pembaca = new FileReader();
      pembaca.onload = function (hasil) {
        gambar.src = hasil.target.result;
        gambar.classList.remove("hidden");
        var pengganti = document.getElementById("posterPh");   // kotak "POSTER" diganti gambar asli
        if (pengganti) pengganti.parentNode.removeChild(pengganti);
      };
      pembaca.readAsDataURL(berkas);
    });
  });

  // checkbox "pilih semua" di tabel keranjang
  document.querySelectorAll("[data-pilih-semua]").forEach(function (induk) {
    induk.addEventListener("change", function () {
      document.querySelectorAll(".pick").forEach(function (kotak) { kotak.checked = induk.checked; });
    });
  });

  // dropdown yang langsung ngirim form begitu diganti (ganti peran di admin)
  document.querySelectorAll("select[data-auto-submit]").forEach(function (pilihan) {
    pilihan.addEventListener("change", function () { pilihan.form.submit(); });
  });


  /* ==============================================================
     3. TOMBOL KERANJANG ♥ (tanpa reload halaman)
     ============================================================== */
  document.addEventListener("submit", function (event) {
    var formulir = event.target;
    if (!formulir.matches("form[data-fav]") || !window.fetch) return;
    event.preventDefault();

    var tombol = formulir.querySelector("button");
    var dataForm = new FormData(formulir);
    dataForm.append("ajax", "1");
    tombol.disabled = true;

    // sengaja pakai getAttribute("action"), bukan formulir.action. dulu ada input bernama "action"
    // yang nimpa properti itu dan bikin bug seharian. trauma, jadi jangan diganti 🙃
    fetch(formulir.getAttribute("action"), { method: "POST", body: dataForm, credentials: "same-origin" })
      .then(function (respons) { return respons.json(); })
      .then(function (hasil) {
        if (!hasil.berhasil) {
          if (hasil.alihkan) window.location = hasil.alihkan;
          return;
        }
        updateTombolKeranjang(dataForm.get("idAcara"), hasil.inKeranjang);
        updateJumlahKeranjang(hasil.jumlah);
      })
      .catch(function () { formulir.submit(); })   // fetch gagal? ya udah, cara jadul aja
      .then(function () { tombol.disabled = false; });
  });

  function updateTombolKeranjang(idAcara, inKeranjang) {
    document.querySelectorAll('form[data-fav] input[name=idAcara][value="' + idAcara + '"]').forEach(function (input) {
      var tombol = input.form.querySelector("button");
      tombol.classList.toggle("on", inKeranjang);
      tombol.setAttribute("aria-pressed", inKeranjang ? "true" : "false");
      tombol.title = inKeranjang ? "Hapus dari keranjang" : "Tambah ke keranjang";
      var teks = input.form.querySelector("[data-fav-label]");
      if (teks) teks.textContent = inKeranjang ? "Di Keranjang ✓" : "+ Keranjang";
    });
  }

  function updateJumlahKeranjang(jumlah) {
    var tautan = document.querySelector('.mainnav a[href$="keranjang.php"]');
    if (!tautan) return;
    var lencana = tautan.querySelector(".count");
    if (jumlah > 0) {
      if (!lencana) {
        lencana = document.createElement("span");
        lencana.className = "count";
        tautan.appendChild(document.createTextNode(" "));
        tautan.appendChild(lencana);
      }
      lencana.textContent = jumlah;
    } else if (lencana) {
      lencana.parentNode.removeChild(lencana);
    }
  }


  /* ==============================================================
     4. CEK USERNAME / EMAIL WAKTU NGETIK (register & profil)
     ============================================================== */

  // debounce = tunggu user berhenti ngetik dulu baru nanya ke server.
  // kalau tiap huruf langsung nanya, servernya bisa ngambek
  function debounce(fungsi, jeda) {
    var timer;
    return function () {
      clearTimeout(timer);
      timer = setTimeout(fungsi, jeda);
    };
  }

  function setStatus(input, elemenPesan, isValid, teks) {
    input.classList.toggle("is-valid", isValid === true);
    input.classList.toggle("is-invalid", isValid === false);
    if (!elemenPesan) return;
    elemenPesan.textContent = teks || "";
    elemenPesan.className = "field-msg" + (isValid === true ? " ok" : isValid === false ? " bad" : "");
  }

  document.querySelectorAll("input[data-check]").forEach(function (input) {
    var kolom = input.getAttribute("data-check");
    var elemenPesan = document.getElementById(input.id + "Pesan");
    var exclude = input.hasAttribute("data-exclude") ? "&exclude=1" : "";
    var nilaiAwal = input.value;

    input.addEventListener("input", debounce(function () {
      var nilai = input.value.trim();
      if (nilai === "" || (exclude && nilai === nilaiAwal)) return setStatus(input, elemenPesan, null, "");
      setStatus(input, elemenPesan, null, "Mengecek…");
      fetch("api.php?aksi=cek&field=" + kolom + "&value=" + encodeURIComponent(nilai) + exclude, { credentials: "same-origin" })
        .then(function (respons) { return respons.json(); })
        .then(function (hasil) {
          if (input.value.trim() === nilai) setStatus(input, elemenPesan, hasil.tersedia, hasil.pesan);
        })
        .catch(function () { setStatus(input, elemenPesan, null, ""); });
    }, 400));
  });

  var inputSandi = document.getElementById("kataSandi");
  var inputUlangi = document.getElementById("ulangiKataSandi");
  function checkUlangi() {
    if (!inputUlangi || inputUlangi.value === "") return;
    var isSama = inputUlangi.value === inputSandi.value;
    setStatus(inputUlangi, document.getElementById("ulangiKataSandiPesan"), isSama, isSama ? "Cocok ✓" : "Belum sama dengan password.");
  }
  if (inputSandi && inputUlangi) {   // cuma di halaman daftar (di halaman login nggak ada kolom ulangi)
    inputSandi.addEventListener("input", function () {
      var isCukup = inputSandi.value.length >= 8;
      setStatus(inputSandi, document.getElementById("kataSandiPesan"), isCukup,
        isCukup ? "Password kuat ✓" : "Minimal 8 karakter (" + inputSandi.value.length + "/8).");
      checkUlangi();
    });
    inputUlangi.addEventListener("input", checkUlangi);
  }


  /* ==============================================================
     5. GEOLOCATION 📍
     Browser bakal nanya izin dulu. Kalau ditolak, ya udah, nggak maksa.
     ============================================================== */
  function getLokasi(onBerhasil, onGagal) {
    if (!("geolocation" in navigator)) return onGagal("Browser kamu tidak mendukung geolocation.");
    navigator.geolocation.getCurrentPosition(
      function (posisi) { onBerhasil(posisi.coords.latitude, posisi.coords.longitude); },
      function (error) {
        onGagal({
          1: "Izin lokasi ditolak. Aktifkan izin lokasi di browser.",
          2: "Lokasi tidak bisa ditentukan.",
          3: "Waktu habis saat mencari lokasi."
        }[error.code] || "Gagal mengambil lokasi.");
      },
      { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 }
    );
  }

  // halaman jelajah: kirim koordinat ke PHP, biar PHP yang ngitung jaraknya
  var tombolLokasi = document.getElementById("geoBtn");
  if (tombolLokasi) {
    tombolLokasi.addEventListener("click", function () {
      var status = document.getElementById("geoStatus");
      status.textContent = "Mencari lokasimu…";
      tombolLokasi.disabled = true;
      getLokasi(function (lintang, bujur) {
        var param = new URLSearchParams(window.location.search);
        param.set("lat", lintang.toFixed(6));
        param.set("lng", bujur.toFixed(6));
        param.set("sort", "jarak");
        window.location.search = param.toString();
      }, function (pesan) {
        status.textContent = pesan;
        tombolLokasi.disabled = false;
      });
    });
  }


  /* ==============================================================
     6. FORM EVENT (penyelenggara)
     ============================================================== */
  var tombolLokasiSaya = document.getElementById("useMyLocation");
  if (tombolLokasiSaya) {
    var inputLintang = document.getElementById("lintang");
    var inputBujur = document.getElementById("bujur");
    var peta = document.getElementById("mapPreview");

    var updatePeta = function () {
      var la = parseFloat(inputLintang.value), lo = parseFloat(inputBujur.value), d = 0.004;
      if (isNaN(la) || isNaN(lo)) return peta.classList.add("hidden");
      peta.src = "https://www.openstreetmap.org/export/embed.html?bbox=" + (lo - d) + "," + (la - d) + "," + (lo + d) + "," + (la + d) +
                 "&layer=mapnik&marker=" + la + "," + lo;
      peta.classList.remove("hidden");
    };

    tombolLokasiSaya.addEventListener("click", function () {
      var pesan = document.getElementById("locMsg");
      pesan.textContent = "Mencari lokasimu…";
      getLokasi(function (lintang, bujur) {
        inputLintang.value = lintang.toFixed(7);
        inputBujur.value = bujur.toFixed(7);
        pesan.textContent = "Lokasi terisi ✓";
        updatePeta();
      }, function (teks) { pesan.textContent = teks; });
    });
    inputLintang.addEventListener("change", updatePeta);
    inputBujur.addEventListener("change", updatePeta);
  }

  // pilih Keatmajayaan → muncul pilihan LDPKM / UKM Fair / Jogja Istimewa / PKKMB + centang susulan
  var kotakKaj = document.getElementById("kajBox");
  if (kotakKaj) {
    var pilihJenisKaj = document.getElementById("jenisKeatmajayaan");
    document.querySelectorAll("[data-kategori]").forEach(function (radio) {
      radio.addEventListener("change", function () {
        var isKaj = radio.checked && radio.getAttribute("data-kategori") === "KAJ";
        kotakKaj.classList.toggle("hidden", !isKaj);
        if (isKaj) pilihJenisKaj.setAttribute("required", "required");
        else { pilihJenisKaj.removeAttribute("required"); pilihJenisKaj.value = ""; }
      });
    });
  }

  // pilih "Online" → kolom koordinat disembunyiin. Zoom kan nggak punya alamat 😄
  document.querySelectorAll("[data-mode]").forEach(function (radio) {
    radio.addEventListener("change", function () {
      var isDaring = radio.value === "online" && radio.checked;
      document.getElementById("coordBox").classList.toggle("hidden", isDaring);
      document.getElementById("lokasiLabel").firstChild.nodeValue = isDaring ? "Platform " : "Nama lokasi ";
      document.getElementById("lokasi").placeholder = isDaring ? "Zoom / Google Meet" : "Gedung Bonaventura, Kampus 3";
    });
  });


  /* ==============================================================
     7. HALAMAN UTAMA (index.xhtml)
     index.xhtml itu XHTML murni, jadi datanya diambil dari api.php
     terus dirakit pakai DOM (bukan innerHTML, soalnya XHTML galak).
     ============================================================== */
  var wadahAcara = document.getElementById("eventGrid");
  if (!wadahAcara) return;   // bukan halaman utama? selesai, bubar jalan

  var alamatServer = "http://localhost/spamaku/";   // ganti kalau nama folder di htdocs beda

  // bikin elemen HTML. createElemen("a", {href: "x", teks: "halo"}) → <a href="x">halo</a>
  function createElemen(tag, atribut, anak) {
    var el = document.createElement(tag);
    Object.keys(atribut || {}).forEach(function (nama) {
      if (nama === "teks") el.textContent = atribut[nama];
      else el.setAttribute(nama, atribut[nama]);
    });
    (anak || []).forEach(function (isi) {
      if (isi) el.appendChild(typeof isi === "string" ? document.createTextNode(isi) : isi);
    });
    return el;
  }

  // dibuka dobel-klik (file://)? PHP nggak jalan, jadi kasih tau user + arahin link ke localhost
  if (window.location.protocol === "file:") {
    document.getElementById("serverNotice").classList.remove("hidden");
    document.querySelectorAll("a[href]").forEach(function (a) {
      var alamat = a.getAttribute("href");
      if (/\.php(\?|$)/.test(alamat)) a.setAttribute("href", alamatServer + alamat);
    });
    document.getElementById("eventPlaceholder").textContent = "Data event akan muncul setelah website dijalankan lewat localhost.";
    return;
  }

  fetch("api.php?aksi=landing", { credentials: "same-origin" })
    .then(function (respons) {
      if (!respons.ok) throw new Error("HTTP " + respons.status);
      return respons.json();
    })
    .then(renderData)
    .catch(function () {
      var info = document.getElementById("serverNotice");
      info.textContent = "Gagal memuat data dari server. Pastikan MySQL menyala dan database \"spamaku\" sudah di-import.";
      info.classList.remove("hidden");
      document.getElementById("eventPlaceholder").textContent = "Event belum bisa dimuat.";
    });

  function renderData(data) {
    var kategoriPerId = {};
    data.kategori.forEach(function (k) { kategoriPerId[k.id] = k; });

    Object.keys(data.statistik).forEach(function (kunci) {
      var el = document.querySelector('[data-stat="' + kunci + '"]');
      if (el) el.textContent = data.statistik[kunci];
    });

    var totalMinimum = data.kategori.reduce(function (jumlah, k) { return jumlah + k.minimum; }, 0);
    document.getElementById("katTotal").textContent = data.kategori.length + " kategori · total " + totalMinimum + " poin";
    var wadahKategori = document.getElementById("katTiles");
    wadahKategori.textContent = "";
    data.kategori.forEach(function (k) {
      wadahKategori.appendChild(createElemen("div", { "class": "kat-tile reveal is-visible", style: "--c:" + k.warna }, [
        createElemen("b", { teks: String(k.minimum) }),
        createElemen("p", { teks: k.nama })
      ]));
    });

    if (data.pengguna) renderMenuPengguna(data.pengguna);

    wadahAcara.textContent = "";
    if (!data.acara.length) {
      wadahAcara.appendChild(createElemen("div", { "class": "card empty", teks: "Belum ada event mendatang." }));
      return;
    }
    data.acara.forEach(function (acara) { wadahAcara.appendChild(createKartuAcara(acara, kategoriPerId, data)); });
  }

  // versi JS dari kartuAcara() di inti.php. kembar tapi beda bahasa
  function createKartuAcara(acara, kategoriPerId, data) {
    var pertama = acara.poin.length ? kategoriPerId[acara.poin[0].kategori] : null;
    var media = createElemen("div", { "class": "event-card__media" }, [
      acara.poster
        ? createElemen("img", { src: acara.poster, alt: "", loading: "lazy" })
        : createElemen("div", { "class": "poster-ph", style: "--c:" + (pertama ? pertama.warna : "#F3AD1B"), teks: pertama ? pertama.singkatan : "EVT" }),
      createElemen("span", { "class": "event-card__date", teks: acara.tanggal })
    ]);

    var formKeranjang = data.pengguna ? createElemen("form", { method: "post", action: "keranjang.php", "class": "event-card__fav", "data-fav": "" }, [
      createElemen("input", { type: "hidden", name: "csrf", value: data.tokenCsrf }),
      createElemen("input", { type: "hidden", name: "aksi", value: "toggle" }),
      createElemen("input", { type: "hidden", name: "idAcara", value: String(acara.id) }),
      createElemen("button", {
        type: "submit",
        "class": "fav-btn" + (acara.inKeranjang ? " on" : ""),
        "aria-pressed": acara.inKeranjang ? "true" : "false",
        title: acara.inKeranjang ? "Hapus dari keranjang" : "Tambah ke keranjang",
        teks: "♥"
      })
    ]) : null;

    var lencana = acara.poin.map(function (p) {
      var k = kategoriPerId[p.kategori];
      return k ? createElemen("span", { "class": "badge-poin", style: "--c:" + k.warna, title: k.nama, teks: "+" + p.poin + " " + k.singkatan }) : null;
    });
    if (acara.keatmajayaan) lencana.push(createElemen("span", { "class": "pill pill-info", teks: acara.keatmajayaan }));   // contoh: "PKKMB · Susulan"

    return createElemen("article", { "class": "event-card glow-hover" }, [
      media,
      formKeranjang,
      createElemen("div", { "class": "event-card__body" }, [
        createElemen("div", { "class": "badges" }, lencana),
        createElemen("h3", {}, [createElemen("a", { href: "jelajah.php?id=" + acara.id, teks: acara.judul })]),
        createElemen("div", { "class": "event-card__meta" }, [
          createElemen("span", { teks: "🏛️ " + acara.organisasi }),
          createElemen("span", { teks: acara.mode === "online" ? "💻 Online" : "📍 " + (acara.lokasi || "-") })
        ]),
        createElemen("div", { "class": "event-card__foot" }, [
          createElemen("b", { teks: acara.biaya }),
          createElemen("span", { "class": "muted", teks: "+" + acara.totalPoin + " poin " + (pertama ? pertama.nama : "SPAMA") })
        ])
      ])
    ]);
  }

  // udah login? tombol Masuk/Daftar diganti menu mahasiswa + foto profil
  function renderMenuPengguna(pengguna) {
    var daftarMenu = document.getElementById("navLinks");
    daftarMenu.textContent = "";
    [["dashboard.php", "Dashboard"], ["jelajah.php", "Jelajah"], ["keranjang.php", "Keranjang"]].forEach(function (m) {
      var a = createElemen("a", { href: m[0], teks: m[1] });
      if (m[0] === "keranjang.php" && pengguna.keranjang > 0) {
        a.appendChild(document.createTextNode(" "));
        a.appendChild(createElemen("span", { "class": "count", teks: String(pengguna.keranjang) }));
      }
      daftarMenu.appendChild(createElemen("li", {}, [a]));
    });

    var areaMasuk = document.getElementById("authArea");
    areaMasuk.textContent = "";
    areaMasuk.appendChild(createElemen("details", { "class": "usermenu" }, [
      createElemen("summary", {}, [
        createElemen("img", { src: pengguna.foto, alt: "", "class": "avatar avatar-sm", width: "32", height: "32" }),
        createElemen("span", { "class": "usermenu__name", teks: pengguna.namaPengguna })
      ]),
      createElemen("div", { "class": "usermenu__panel" }, [
        createElemen("p", { "class": "usermenu__mode", teks: "Mode" }),
        createElemen("a", { href: "dashboard.php", teks: "🎓 Mahasiswa" }),
        createElemen("a", { href: "penyelenggara.php", teks: "🎪 Penyelenggara" }),
        pengguna.admin ? createElemen("a", { href: "admin.php", teks: "🛡️ Admin" }) : null,
        createElemen("hr", {}),
        createElemen("a", { href: "profil.php", teks: "Profil Saya" }),
        createElemen("a", { href: "masuk.php?keluar=1", "class": "danger", teks: "Keluar" })
      ])
    ]));

    var tombolKedua = document.getElementById("heroSecondary");
    tombolKedua.setAttribute("href", "dashboard.php");
    tombolKedua.textContent = "Lihat Progresku";
    document.getElementById("ctaOrg").setAttribute("href", "penyelenggara.php?hal=organisasi");
  }
})();
