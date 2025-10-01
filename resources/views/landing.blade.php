<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Landing Page | Pembayaran SKS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=swap" rel="stylesheet">

    {{-- Bootstrap 5 CDN --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">


    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            overflow-x: hidden;
            background-color: #14532d;
        }

        .custom-navbar {
            background-color:rgb(68, 145, 17);
            padding: 1.8rem 1rem;
            transition: all 0.4s ease;
            box-shadow: none;
            height: 100px; /* sesuaikan dengan besar navbar */
            z-index: 2000; /* FIX: pastikan di atas elemen lain, tombol bisa diklik */
        }

        .custom-navbar .navbar-brand,
        .custom-navbar a {
            color: white !important;
            transition: color 0.3s ease;
        }

        /* Saat scroll: navbar kecil dan putih */
        .custom-navbar.scrolled {
            background-color: #ffffff !important;
            padding: 0.8rem 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .custom-navbar.scrolled .navbar-brand,
        .custom-navbar.scrolled a {
            color: #14532d !important; /* hijau gelap atau sesuai tema */
        }

        /* Memastikan teks di dalam tombol tetap putih meski navbar berubah */
        .custom-navbar.scrolled .btn-primary,
        .custom-navbar.scrolled .btn-secondary,
        .custom-navbar.scrolled .btn-primary *,
        .custom-navbar.scrolled .btn-secondary * {
            color: #fff !important;
        }




        .banner-container {
            padding-top: 100px; /* Sama seperti tinggi navbar */
            background-color: #14532d;
        }

        /* banner */
        #heroBanner .carousel-inner img {
            width: 100vw;
            max-height: 95vh;
            object-fit: contain;
            background-color: #004225; /* Sesuaikan dengan tema agar harmonis */
            padding: 1rem; /* Biar tidak mepet */
            display: block;
            margin: 0 auto;
        }



        @media (max-width: 768px) {
            #heroBanner .carousel-inner img {
                height: 60vh;
            }
        }

        #heroBanner .carousel-control-prev-icon,
        #heroBanner .carousel-control-next-icon {
            background-size: 100% 100%;
            width: 3rem;
            height: 3rem;
            filter: brightness(0) invert(1);
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
        }

        #heroBanner .carousel-control-prev,
        #heroBanner .carousel-control-next {
            width: 5%;
        }

        .btn-lg {
            font-weight: bold;
            padding: 0.75rem 2rem;
        }

        /* SECTION KATA PENGANTAR */
        .kata-pengantar-section {
            background: linear-gradient(to bottom, #14532d 90%, #0f4225 100%);
            color: white;
            padding: 5rem 1rem;
        }

        .kata-pengantar-section h2 {
            color: #ffc107;
            font-weight: 700;
            font-size: 2.4rem;
            margin-bottom: 2.5rem;
            text-align: center;
        }

        .kata-pengantar-section .gedung-img {
            width: 100%;
            max-width: 1100px;
            border-radius: 16px;
            box-shadow: 0 6px 30px rgba(0,0,0,0.3);
            margin: 0 auto 2.5rem;
            display: block;
        }


        .kata-pengantar-section .deskripsi {
            font-size: 1.5rem;
            line-height: 1.9;
            color: #f0f0f0;
            max-width: 1100px;
            margin: 0 auto;
            text-align: justify;
            font-family: 'Poppins', sans-serif;
        }

       .kata-pengantar-img {
            max-width: 100%;
            height: auto;
            border-radius: 16px;
            box-shadow: 0 6px 30px rgba(0, 0, 0, 0.3);
            background-color: #004225; /* optional */
            padding: 0; /* sebelumnya 1rem */
        }

        /* Visi misi */ 
        .visi-misi-section {
            background: linear-gradient(to bottom, #14532d 90%, #0f4225 100%);
            color: white;
            padding: 5rem 1rem;
        }

        .section-heading-yellow {
            color: #ffc107;
            font-weight: 700;
            font-size: 2.4rem;
            margin-bottom: 2.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .visi-misi-img {
            width: 230px;
            height: 230px;
            object-fit: cover;
            border-radius: 50%;
            border: 6px solid #ffffff;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }

        .visi-misi-img:hover {
            transform: scale(1.08);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        .subheading-white {
            font-weight: 700;
            font-size: 1.8rem;
            color: #ffffff;
            margin-bottom: 1rem;
            font-family: 'Poppins', sans-serif;
        }

        .visi-misi-text {
            font-size: 1.3rem;
            line-height: 1.8;
            color: #f0f0f0;
            font-family: 'Poppins', sans-serif;
            text-align: justify;
        }

        .visi-misi-list {
            font-size: 1.3rem;
            line-height: 1.8;
            color: #f0f0f0;
            font-family: 'Poppins', sans-serif;
            padding-left: 1.2rem;
            text-align: justify;
        }




        /* SECTION POSTER */
        #posterSection {
            background-color: #14532d;
            padding: 3rem 1rem;
            
        }


        #posterSection h2 {
            font-size: 2.2rem;       /* default desktop */
            font-weight: 700;
            text-align: center;
            margin-bottom: 4rem;
            color: #fff;
        }


        .poster-row {
            display: flex;
            justify-content: center;
            gap: 4rem; /* sebelumnya 2.5rem */
            flex-wrap: wrap;
        }


        .poster-item {
            max-width: 500px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            background-color: #fff;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .poster-item:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 30px rgba(0,0,0,0.5);
        }

        .poster-item img {
            width: 100%;
            height: auto;
            display: block;
        }

        .section-title {
            position: relative;
            display: inline-block;
            padding-bottom: 1.5rem;
            color: #fff;
            font-weight: 700;
            font-size: 2.2rem;
            line-height: 1.4;
        }

        .section-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            height: 4px;
            width: 100%;
            background-color: #ffc107;
            border-radius: 2px;
        }

        
        /* Pengurus */
        .pengurus-section {
            background: linear-gradient(to bottom, #14532d 90%,rgb(54, 143, 54) 100%);
            padding: 5rem 1rem;
            color: #fff;
            margin-top: 1rem; /* opsional tambahan jarak dari atas */
        }

        .pengurus-item {
            padding: 0 1rem;
            margin-bottom: 2rem;
        }

        .section-heading {
            display: inline-block;
            font-size: 2.5rem;
            font-weight: 700;
            padding: 0.6rem 2.5rem;
            border-radius: 999px;
            background-color: #ffffff; /* oval putih */
            border: none;
            color: #14532d; /* hijau gelap */
            font-family: 'Poppins', sans-serif;
            margin-bottom: 3rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }


        .foto-bersama {
            width: 100%;
            max-width: 800px;
            height: auto;
            border-radius: 16px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.3);
            display: block;
            margin-left: auto;
            margin-right: auto;
            transition: transform 0.3s ease;
        }

        .foto-bersama:hover {
            transform: scale(1.02);
        }

        .foto-profil {
            width: 270px;
            height: 270px;
            object-fit: cover;
            border-radius: 50%;
            border: 6px solid #ffffff;
            box-shadow: 0 0 15px rgba(0,0,0,0.3);
            transition: transform 0.3s ease;
            margin-bottom: 1.5rem; /* jarak ke bawah */
        }

        .profil-text-box {
            background-color: rgba(255, 255, 255, 0.1);
            border: 2px solid #ffffff;
            border-radius: 16px;
            padding: 1rem;
            margin-top: 1rem;
            color: #fff;
            max-width: 270px;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(6px);
            transition: all 0.3s ease;
        }

        .profil-text-box h5 {
            font-size: 1.1rem;
            margin-bottom: 0.3rem;
            color: #ffffff;
        }

        .profil-text-box p {
            font-size: 0.9rem;
            color: #dcdcdc;
            margin-bottom: 0;
        }


        .foto-profil:hover {
            transform: scale(1.05);
        }

        .pengurus-item h5 {
            color: #fff;
        }

        .pengurus-item p {
            color: #dcdcdc;
        }


        .kurikulum-section {
            position: relative;
            background-image: url('{{ asset("images/background.jpeg") }}');
            background-size: cover;
            background-position: center;
            padding: 5rem 1rem;
            overflow: hidden;
        }



        .overlay-green {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, #14532d 90%, rgb(54, 143, 54) 100%);
            opacity: 0.85;
            z-index: 1;
        }


        .kurikulum-section .container {
            position: relative;
            z-index: 2;
        }

        .kurikulum-card {
            background-color: rgba(255, 255, 255, 0.07);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 2rem;
            color: #ffffff;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.25);
            backdrop-filter: blur(6px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .kurikulum-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.4);
        }

        .kurikulum-title {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #ffc107;
            font-family: 'Poppins', sans-serif;
        }

        .kurikulum-card ul {
            list-style-type: none;
            padding-left: 0;
            text-align: left;
        }

        .kurikulum-card ul li {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            padding-left: 1.2rem;
            position: relative;
        }

        .kurikulum-card ul li::before {
            content: '📘';
            position: absolute;
            left: 0;
            top: 0;
        }

        .total-sks {
            margin-top: 1rem;
            font-weight: bold;
            font-size: 1.2rem;
            color: #ffe082;
        }

        /* Responsive tweak */
        @media (max-width: 768px) {
            .kurikulum-card {
                margin-bottom: 1.5rem;
            }
        }



        /* Footer */

        .footer-unissula {
            background-color: #0c4b2f;
            color: #fff;
            padding: 5rem 3rem 2rem 3rem;
            font-family: 'Poppins', sans-serif;
            font-size: 1.3rem;
        }

        .footer-unissula h5 {
            font-size: 1.7rem;
            font-weight: 700;
            margin-bottom: 1.2rem;
            color: #ffffff;
        }

        .footer-unissula p {
            font-size: 1.25rem;
            margin-bottom: 0.6rem;
            line-height: 1.6;
            color: #e0e0e0;
        }

        .footer-unissula i {
            color: #ffc107;
            margin-right: 10px;
            font-size: 1.5rem;
        }

        .footer-unissula .social-icons i {
            font-size: 1.8rem;
        }

        .footer-unissula .social-icons a:hover i {
            color: #ffc107;
        }

        .footer-unissula img {
            max-width: 300px; /* logo lebih besar */
            margin-bottom: 1rem;
        }

        .footer-unissula .fst-italic {
            font-size: 1.3rem;
            color: #ccc;
            font-style: italic;
        }

        .footer-unissula hr {
            border-color: rgba(255, 255, 255, 0.3);
            margin-top: 3rem;
            margin-bottom: 1.5rem;
        }

       .footer-unissula .footer-bottom {
            display: flex;
            justify-content: space-between; /* Ubah dari center */
            align-items: center;
            flex-wrap: wrap;
            padding: 1.5rem 2rem 0;
            font-size: 1.1rem;
        }



        /* RESPONSIF UNTUK MOBILE */
        @media (max-width: 768px) {
            .section-title {
                font-size: 1.5rem;
            }

            #posterSection h2 {
                font-size: 1.6rem;
            }
        }
    </style>
</head>

<body class="bg-light">

    {{-- NAVBAR --}}
    <nav id="mainNavbar" class="navbar navbar-expand-lg fixed-top custom-navbar">
        <div class="container-fluid justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="{{ asset('images/unissula.png') }}" alt="Logo" width="60" height="60" class="me-3">
                <span class="fw-semibold fs-4">Magister Pendidikan Agama Islam</span>
            </a>
            <div class="d-flex align-items-center">
<a href="{{ route('login') }}" class="btn btn-primary btn-lg me-3">🔑 Login Mahasiswa</a>
<a href="{{ route('admin.login') }}" class="btn btn-secondary btn-lg">🛠️ Login Admin</a>

            </div>
        </div>
    </nav>


    {{-- HERO BANNER --}}
    <div class="banner-container">
        <div id="heroBanner" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="{{ asset('images/5.jpg') }}" alt="Banner Utama" class="d-block w-100">
                </div>
                {{-- Tambahkan slide lain jika perlu --}}
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroBanner" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroBanner" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
        </div>
    </div>    

    <!-- KATA PENGANTAR SECTION -->
    <section class="kata-pengantar-section">
        <div class="container">
            <h2>📜 Kata Pengantar</h2>

            <div class="text-center mb-4">
                <img src="{{ asset('images/kata_pengantar.png') }}" alt="Gedung UNISSULA" class="d-block w-100 kata-pengantar-img">
            </div>

            <div class="deskripsi">
                <p>
                    UNISSULA merupakan perguruan tinggi Islam swasta (PTIS) paling tua di wilayah Jawa Tengah yang telah berpengalaman menyelenggarakan pendidikan tinggi lebih dari setengah abad. Hal ini dibuktikan dengan jumlah mahasiswa sekitar 17.000 dan jumlah alumni lebih dari 30.000. Perguruan Islam ini beroperasi di bawah pengelolaan Yayasan Badan Wakaf Sultan Agung (YBWSA) yang memiliki capaian institusi akreditasi A serta dengan fasilitas pendidikan dan pembelajaran yang representatif.
                </p>
                <p>
                    Hingga saat ini, UNISSULA memiliki 12 Fakultas dengan 29 Program Studi, 9 Program Magister (S2) dan 3 Program Doktor (S3). Salah satu di antara Program Magister tersebut adalah Magister Pendidikan Agama Islam (MPAI) yang diselenggarakan berdasarkan keputusan Direktur Jenderal Islam Kementerian Agama Republik Indonesia Nomor: Dj.I/185/2010. Dan sejak tanggal 9 Juni 2020, Magister Pendidikan Agama Islam terakreditasi B oleh BAN-PT berdasarkan SK No. 3413/SK/BAN-PT/Akred/M/VI/2020.
                </p>
            </div>
        </div>
    </section>


    <!-- VISI & MISI SECTION -->
    <section class="visi-misi-section py-5">
        <div class="container text-white">
            <h2 class="section-heading-yellow text-center mb-5">🌟 Visi & Misi</h2>

            <div class="row align-items-center mb-5">
                <!-- Gambar Visi -->
                <div class="col-md-4 text-center mb-4 mb-md-0">
                    <img src="{{ asset('images/Visi.jpg') }}" alt="Visi Icon" class="visi-misi-img">
                </div>

                <!-- Isi Visi -->
                <div class="col-md-8">
                    <h4 class="subheading-white">Visi</h4>
                    <p class="visi-misi-text">
                        Sebagai Program Magister Pendidikan Agama Islam yang terdepan dalam sistem terintegrasi antara keilmuan, teknologi pendidikan Islam, serta pengembangan peradaban Islam yang rahmatan lil alamin.
                    </p>
                </div>
            </div>

            <div class="row align-items-center flex-md-row-reverse">
                <!-- Gambar Misi -->
                <div class="col-md-4 text-center mb-4 mb-md-0">
                    <img src="{{ asset('images/misi.jpg') }}" alt="Misi Icon" class="visi-misi-img">
                </div>

                <!-- Isi Misi -->
                <div class="col-md-8">
                    <h4 class="subheading-white">Misi</h4>
                    <ol class="visi-misi-list">
                        <li>Menyelenggarakan program Magister PAI yang berkualitas dan berlandaskan nilai-nilai keislaman.</li>
                        <li>Menyiapkan sumber daya unggul dan profesional dalam pendidikan Islam.</li>
                        <li>Mendorong riset dan publikasi ilmiah dalam pengembangan pendidikan dan teknologi Islam.</li>
                        <li>Berperan aktif dalam pembangunan masyarakat melalui pengabdian berbasis nilai Islam rahmatan lil alamin.</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>



    {{-- POSTER SECTION --}}
    <section id="posterSection">
        <h2><span class="section-title">🎓 Promo Terbatas! Dapatkan Diskon Hingga 50% untuk Pendaftaran Magister PAI UNISSULA 🎓</span></h2>
        <div class="poster-row">
            <div class="poster-item">
                <img src="{{ asset('images/poster-1.jpg') }}" alt="Poster Jalur Reguler">
            </div>
            <div class="poster-item">
                <img src="{{ asset('images/poster-2.jpg') }}" alt="Poster Fast Track">
            </div>
            <div class="poster-item">
                <img src="{{ asset('images/poster-3.jpg') }}" alt="Poster RPL">
            </div>
        </div>
    </section>

    <!-- Kepengurusan -->
    <section id="pengurusSection" class="pengurus-section">
        <div class="container text-center text-white">
            <h2 class="section-heading">Pengurus MPAI UNISSULA</h2>

            <!-- FOTO BERSAMA: 2 di atas -->
            <div class="row justify-content-center g-4 mb-4">
                <div class="col-lg-6 col-md-12">
                    <img src="{{ asset('images/bersama-1.jpg') }}" class="foto-bersama" alt="Foto Bersama 1">
                </div>
                <div class="col-lg-6 col-md-12">
                    <img src="{{ asset('images/bersama-2.jpg') }}" class="foto-bersama" alt="Foto Bersama 2">
                </div>
            </div>

            <!-- FOTO BERSAMA: 1 di tengah bawah -->
            <div class="row justify-content-center mb-5">
                <div class="col-lg-8 col-md-10">
                    <img src="{{ asset('images/bersama-3.jpg') }}" class="foto-bersama" alt="Foto Bersama 3">
                </div>
            </div>

            <div class="row justify-content-center g-4 mb-4">
                <div class="col-md-4">
                    <div class="pengurus-item text-center">
                    <img src="{{ asset('images/bu_muna.png') }}" class="foto-profil" alt="Sek. Prodi">
                    <div class="profil-text-box">
                        <h5 class="fw-bold">Dr. Muna Madrah, MA</h5>
                        <p>Sekretaris Prodi</p>
                    </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="pengurus-item text-center">
                    <img src="{{ asset('images/pak_agus.png') }}" class="foto-profil" alt="Ka. Prodi">
                    <div class="profil-text-box">
                        <h5 class="fw-bold">Dr. Agus Irfan, MPI</h5>
                        <p>Ketua Prodi</p>
                    </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="pengurus-item text-center">
                    <img src="{{ asset('images/pak_zaki.png') }}" class="foto-profil" alt="Keuangan">
                    <div class="profil-text-box">
                        <h5 class="fw-bold">M. Zakki Mubarok, M.I.Kom</h5>
                        <p>Ka. Admin & Keuangan</p>
                    </div>
                    </div>
                </div>
            </div>


            <div class="row justify-content-center g-4">
                <div class="col-md-3">
                    <div class="pengurus-item text-center">
                        <img src="{{ asset('images/cika_2.png') }}" class="foto-profil" alt="Anggota 1">
                        <div class="profil-text-box">
                            <h5 class="fw-bold">Shicha Alfiyaturohmaniyyah, M.Pd</h5>
                            <p>Staf Admin</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="pengurus-item text-center">
                        <img src="{{ asset('images/mas_ali.png') }}" class="foto-profil" alt="Anggota 2">
                        <div class="profil-text-box">
                            <h5 class="fw-bold">M Alimun Khakim, M.Pd</h5>
                            <p>Staf Admin</p>
                        </div>
                    </div>
                </div>
            </div>


            </div>
        </div>
    </section>

    <!-- KURIKULUM SECTION -->
    <section class="kurikulum-section text-white text-center">
        <div class="overlay-green"></div>
        <div class="container position-relative">
            <h2 class="section-heading-yellow mb-5">📘 Kurikulum Magister PAI</h2>

            <!-- Semester 1 & 2 -->
            <div class="row gx-5 gy-4 mb-4">
                <div class="col-lg-6">
                    <div class="kurikulum-card">
                        <h4 class="kurikulum-title">Semester 1</h4>
                        <ul>
                            <li>Peradaban Islam – 2 SKS</li>
                            <li>Metode Studi Islam – 2 SKS</li>
                            <li>Filsafat Ilmu dan Epistemologi Islam – 3 SKS</li>
                            <li>Studi Pemikiran Tokoh Pendidikan Islam – 3 SKS</li>
                            <li>Studi Alquran dan Hadis – 2 SKS</li>
                        </ul>
                        <p class="total-sks">Jumlah: 12 SKS</p>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="kurikulum-card">
                        <h4 class="kurikulum-title">Semester 2</h4>
                        <ul>
                            <li>Pengembangan Sistem Pembelajaran berbasis TI – 3 SKS</li>
                            <li>Pengembangan Kurikulum – 3 SKS</li>
                            <li>Pengembangan Evaluasi Pendidikan – 3 SKS</li>
                            <li>Seminar Pendidikan Islam – 2 SKS</li>
                            <li>Metodologi Penelitian Pendidikan – 3 SKS</li>
                        </ul>
                        <p class="total-sks">Jumlah: 14 SKS</p>
                    </div>
                </div>
            </div>

            <!-- Semester 3 & 4 -->
            <div class="row gx-5 gy-4">
                <div class="col-lg-6">
                    <div class="kurikulum-card">
                        <h4 class="kurikulum-title">Semester 3</h4>
                        <ul>
                            <li>Manajemen Mutu Pendidikan – 3 SKS</li>
                            <li>Politik Pendidikan Islam – 3 SKS</li>
                            <li>Psikologi Pendidikan Islam – 3 SKS</li>
                            <li>Manajemen Mutu Pendidikan – 2 SKS</li>
                        </ul>
                        <p class="total-sks">Jumlah: 11 SKS</p>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="kurikulum-card">
                        <h4 class="kurikulum-title">Semester 4</h4>
                        <ul>
                            <li>Tesis – 6 SKS</li>
                        </ul>
                        <p class="total-sks">Jumlah: 6 SKS</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

   

    <footer class="footer-unissula text-white pt-5">
        <div class="container-fluid px-5">
            <div class="row gy-4 align-items-start">

                <!-- Logo dan moto -->
                <div class="col-lg-3 text-center">
                    <img src="{{ asset('images/unissula.png') }}" alt="Logo UNISSULA" class="img-fluid mb-3" style="max-width: 200px;">
                    <p class="fst-italic fs-6">Bismillah Membangun Generasi Khaira Ummah</p>
                </div>

                <!-- Kontak Semarang -->
                <div class="col-lg-3">
                    <h5 class="fw-bold fs-5">Magister Pendidikan Agama Islam</h5>
                    <p><i class="bi bi-geo-alt-fill me-2"></i>Jl. Kaligawe Raya No.KM. 4, Semarang, Jawa Tengah</p>
                    <p><i class="bi bi-telephone-fill me-2"></i>(024) 6583584</p>
                    <p><i class="bi bi-envelope-fill me-2"></i>mpai@unissula.ac.id</p>
                    <p><i class="bi bi-whatsapp me-2"></i>+62 857-2382-1623</p>
                </div>

                <!-- Kontak Pendaftaran -->
                <div class="col-lg-3">
                    <h5 class="fw-bold fs-5">Layanan Pendaftaran</h5>
                    <p><i class="bi bi-person-fill me-2"></i>Ali Munahkim, M.Pd</p>
                    <p><i class="bi bi-whatsapp me-2"></i>+62 857-2382-1623</p>
                    <p><i class="bi bi-envelope-fill me-2"></i>pendaftaran@unissula.ac.id</p>
                </div>

                <!-- Sosial Media -->
                <div class="col-lg-3">
                    <h5 class="fw-bold fs-5">Ikuti Kami</h5>
                    <p><i class="bi bi-globe2 me-2"></i><a href="https://www.unissula.ac.id" class="text-white text-decoration-none">www.unissula.ac.id</a></p>
                    <p><i class="bi bi-facebook me-2"></i>Magister PAI UNISSULA</p>
                    <p><i class="bi bi-instagram me-2"></i>@mpai_unissula</p>
                    <p><i class="bi bi-youtube me-2"></i>MPAI Official</p>
                </div>
            </div>

            <hr class="border-light mt-4">

            <div class="d-flex justify-content-between align-items-center flex-wrap px-2 py-3">
                <p class="copyright mb-0">MAGISTER PAI UNISSULA || Copyright © 2024 Universitas Islam Sultan Agung</p>
                <div class="social-icons mt-2">
                    <a href="#" class="text-white me-3"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="text-white me-3"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="text-white"><i class="bi bi-youtube"></i></a>
                </div>
            </div>
        </div>
    </footer>

        
    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<script>
    window.addEventListener('scroll', function () {
        const navbar = document.getElementById('mainNavbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
</script>

</html>
