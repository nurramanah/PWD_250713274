function fn_ValForm() {
    var sMsg = "";
    var sNama = document.getElementById("nama").value;
    var sEmail = document.getElementById("email").value;
    var sUmur = document.getElementById("umur").value;
    var sPaket = document.getElementById("kategori").value;
    var iUmur;
    var sInfo = "";
    var sPilihan = "";

    var reEmail = /^[a-z0-9][a-z0-9_\.-]{0,}[a-z0-9][@][a-z0-9][a-z0-9_\.-]{0,}[a-z0-9][\.][a-z0-9]{2,4}$/;

    if (sNama == "") {
        sMsg += "\n* Nama belum diisi";
    }

    if (sEmail == "") {
        sMsg += "\n* Email belum diisi";
    } else if (!reEmail.test(sEmail)) {
        sMsg += "\n* Format email tidak valid";
    }

    if (sUmur == "") {
        sMsg += "\n* Umur belum diisi";
    } else if (isNaN(sUmur)) {
        sMsg += "\n* Umur harus berupa angka";
    } else {
        iUmur = parseInt(sUmur, 10);
        if (iUmur < 17 || iUmur > 65) {
            sMsg += "\n* Umur harus berada di antara 17 sampai 65 tahun";
        }
    }

    if (sPaket == "") {
        sMsg += "\n* Paket member belum dipilih";
    }

    if (sMsg != "") {
        alert("Peringatan:" + sMsg);
        return false;
    }

    if (iUmur <= 25) {
        sInfo = "Paket Pelajar";
    } else {
        sInfo = "Paket Umum";
    }

    switch (sPaket) {
        case "S":
            sPilihan = "Silver";
            break;
        case "G":
            sPilihan = "Gold";
            break;
        case "P":
            sPilihan = "Platinum";
            break;
        default:
            alert("Paket member tidak valid");
            return false;
    }

    alert(
        "Terima kasih " + [sNama] + " ! Anda terdaftar di paket " + [sPilihan] + " (" + sInfo + ")."
    );
    return false;
}