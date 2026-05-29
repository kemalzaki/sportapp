<?php
/**
 * Data tata cara shalat (lengkap rakaat fardhu), bacaan Arab + Latin + Terjemah,
 * serta daftar shalat sunnah rawatib.
 * Dipakai di islami.php.
 */

$SHALAT_TATA_CARA = [
  [
    'judul' => '1. Niat (dalam hati)',
    'arab'  => 'أُصَلِّيْ فَرْضَ الصُّبْحِ رَكْعَتَيْنِ مُسْتَقْبِلَ الْقِبْلَةِ أَدَاءً لِلَّهِ تَعَالَى',
    'latin' => 'Ushalli fardhash-shubhi rak‘ataini mustaqbilal-qiblati adā’an lillāhi ta‘ālā.',
    'arti'  => 'Aku berniat shalat fardhu Subuh dua rakaat menghadap kiblat karena Allah Ta‘ala. (Contoh niat shalat Subuh — sesuaikan untuk Zhuhur/Ashar/Maghrib/Isya.)',
  ],
  [
    'judul' => '2. Takbiratul Ihram',
    'arab'  => 'اللهُ أَكْبَر',
    'latin' => 'Allāhu Akbar.',
    'arti'  => 'Allah Maha Besar. (Mengangkat kedua tangan sejajar telinga/bahu lalu bersedekap di dada.)',
  ],
  [
    'judul' => '3. Doa Iftitah',
    'arab'  => 'اللَّهُ أَكْبَرُ كَبِيرًا، وَالْحَمْدُ لِلَّهِ كَثِيرًا، وَسُبْحَانَ اللَّهِ بُكْرَةً وَأَصِيلًا. إِنِّي وَجَّهْتُ وَجْهِيَ لِلَّذِي فَطَرَ السَّمَاوَاتِ وَالْأَرْضَ حَنِيفًا مُسْلِمًا وَمَا أَنَا مِنَ الْمُشْرِكِينَ. إِنَّ صَلَاتِي وَنُسُكِي وَمَحْيَايَ وَمَمَاتِي لِلَّهِ رَبِّ الْعَالَمِينَ. لَا شَرِيكَ لَهُ وَبِذَلِكَ أُمِرْتُ وَأَنَا مِنَ الْمُسْلِمِينَ',
    'latin' => 'Allāhu akbar kabīrā, wal-hamdu lillāhi katsīrā, wa subhānallāhi bukratan wa ashīlā. Innī wajjahtu wajhiya lilladzī fatharas-samāwāti wal-ardha hanīfan musliman wa mā ana minal-musyrikīn. Inna shalātī wa nusukī wa mahyāya wa mamātī lillāhi rabbil-‘ālamīn. Lā syarīka lahu wa bidzālika umirtu wa ana minal-muslimīn.',
    'arti'  => 'Allah Maha Besar dengan sebesar-besarnya. Segala puji yang banyak bagi Allah. Maha Suci Allah pada waktu pagi dan petang. Sungguh aku hadapkan wajahku kepada Dzat yang menciptakan langit dan bumi dalam keadaan lurus lagi berserah diri, dan aku bukanlah termasuk orang-orang musyrik. Sesungguhnya shalatku, ibadahku, hidupku, dan matiku hanyalah untuk Allah, Tuhan semesta alam. Tiada sekutu bagi-Nya. Dengan itulah aku diperintahkan, dan aku termasuk orang-orang yang berserah diri (muslim).',
  ],
  [
    'judul' => '4. Al-Fatihah',
    'arab'  => 'بِسْمِ اللهِ الرَّحْمَٰنِ الرَّحِيمِ ۝ الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ ۝ الرَّحْمَٰنِ الرَّحِيمِ ۝ مَالِكِ يَوْمِ الدِّينِ ۝ إِيَّاكَ نَعْبُدُ وَإِيَّاكَ نَسْتَعِينُ ۝ اهْدِنَا الصِّرَاطَ الْمُسْتَقِيمَ ۝ صِرَاطَ الَّذِينَ أَنْعَمْتَ عَلَيْهِمْ غَيْرِ الْمَغْضُوبِ عَلَيْهِمْ وَلَا الضَّالِّينَ',
    'latin' => 'Bismillāhir-rahmānir-rahīm. Alhamdu lillāhi rabbil-‘ālamīn. Ar-rahmānir-rahīm. Māliki yawmid-dīn. Iyyāka na‘budu wa iyyāka nasta‘īn. Ihdinas-sirātal-mustaqīm. Sirātalladzīna an‘amta ‘alaihim, ghairil-maghdūbi ‘alaihim wa lādh-dhāllīn.',
    'arti'  => 'Dengan nama Allah Yang Maha Pengasih lagi Maha Penyayang. Segala puji bagi Allah Tuhan semesta alam, Yang Maha Pengasih lagi Maha Penyayang, Pemilik hari pembalasan. Hanya kepada-Mu kami menyembah dan hanya kepada-Mu kami memohon pertolongan. Tunjukilah kami jalan yang lurus, (yaitu) jalan orang-orang yang Engkau beri nikmat, bukan (jalan) mereka yang dimurkai dan bukan (pula jalan) mereka yang sesat. (Aamiin).',
  ],
  [
    'judul' => '5. Surah Pendek (contoh Al-Ikhlas)',
    'arab'  => 'قُلْ هُوَ اللَّهُ أَحَدٌ ۝ اللَّهُ الصَّمَدُ ۝ لَمْ يَلِدْ وَلَمْ يُولَدْ ۝ وَلَمْ يَكُنْ لَهُ كُفُوًا أَحَدٌ',
    'latin' => 'Qul huwallāhu ahad. Allāhus-samad. Lam yalid wa lam yūlad. Wa lam yakul-lahu kufuwan ahad.',
    'arti'  => 'Katakanlah: Dialah Allah Yang Maha Esa. Allah tempat bergantung segala sesuatu. Dia tidak beranak dan tidak pula diperanakkan. Dan tidak ada sesuatupun yang setara dengan-Nya.',
  ],
  [
    'judul' => '6. Rukuk',
    'arab'  => 'سُبْحَانَ رَبِّيَ الْعَظِيْمِ وَبِحَمْدِهِ (3×)',
    'latin' => 'Subhāna rabbiyal-‘adzīmi wa bihamdih. (3×)',
    'arti'  => 'Maha Suci Tuhanku Yang Maha Agung dan segala puji bagi-Nya.',
  ],
  [
    'judul' => '7. I’tidal',
    'arab'  => 'سَمِعَ اللهُ لِمَنْ حَمِدَهُ. رَبَّنَا وَلَكَ الْحَمْدُ مِلْءَ السَّمَاوَاتِ وَمِلْءَ الْأَرْضِ وَمِلْءَ مَا شِئْتَ مِنْ شَيْءٍ بَعْدُ',
    'latin' => 'Sami‘allāhu liman hamidah. Rabbanā walakal-hamdu mil’as-samāwāti wa mil’al-ardhi wa mil’a mā syi’ta min syai’in ba‘du.',
    'arti'  => 'Allah mendengar (memperhatikan) orang yang memuji-Nya. Wahai Tuhan kami, bagi-Mu segala puji sepenuh langit, sepenuh bumi, dan sepenuh apa saja yang Engkau kehendaki sesudah itu.',
  ],
  [
    'judul' => '8. Sujud (dua kali)',
    'arab'  => 'سُبْحَانَ رَبِّيَ الْأَعْلَى وَبِحَمْدِهِ (3×)',
    'latin' => 'Subhāna rabbiyal-a‘lā wa bihamdih. (3×)',
    'arti'  => 'Maha Suci Tuhanku Yang Maha Tinggi dan segala puji bagi-Nya.',
  ],
  [
    'judul' => '9. Duduk di antara dua sujud',
    'arab'  => 'رَبِّ اغْفِرْ لِي وَارْحَمْنِي وَاجْبُرْنِي وَارْفَعْنِي وَارْزُقْنِي وَاهْدِنِي وَعَافِنِي وَاعْفُ عَنِّي',
    'latin' => 'Rabbighfirlī, warhamnī, wajburnī, warfa‘nī, warzuqnī, wahdinī, wa ‘āfinī, wa‘fu ‘annī.',
    'arti'  => 'Ya Tuhanku, ampunilah aku, sayangilah aku, cukupilah kekuranganku, angkatlah derajatku, berilah aku rezeki, berilah aku petunjuk, sehatkanlah aku, dan maafkanlah aku.',
  ],
  [
    'judul' => '10. Tasyahud Awal',
    'arab'  => 'التَّحِيَّاتُ الْمُبَارَكَاتُ الصَّلَوَاتُ الطَّيِّبَاتُ لِلَّهِ. السَّلَامُ عَلَيْكَ أَيُّهَا النَّبِيُّ وَرَحْمَةُ اللهِ وَبَرَكَاتُهُ. السَّلَامُ عَلَيْنَا وَعَلَى عِبَادِ اللهِ الصَّالِحِينَ. أَشْهَدُ أَنْ لَا إِلَهَ إِلَّا اللهُ وَأَشْهَدُ أَنَّ مُحَمَّدًا رَسُولُ اللهِ',
    'latin' => 'At-tahiyyātul-mubārakātush-shalawātuth-thayyibātu lillāh. As-salāmu ‘alaika ayyuhan-nabiyyu wa rahmatullāhi wa barakātuh. As-salāmu ‘alainā wa ‘alā ‘ibādillāhish-shālihīn. Asyhadu allā ilāha illallāh, wa asyhadu anna Muhammadar rasūlullāh.',
    'arti'  => 'Segala penghormatan yang berkah, shalawat dan kebaikan adalah milik Allah. Semoga keselamatan, rahmat dan keberkahan Allah terlimpah kepadamu wahai Nabi. Semoga keselamatan tercurah kepada kami dan kepada hamba-hamba Allah yang shalih. Aku bersaksi tiada Ilah selain Allah, dan aku bersaksi bahwa Muhammad adalah utusan Allah.',
  ],
  [
    'judul' => '11. Tasyahud Akhir + Shalawat',
    'arab'  => 'اللَّهُمَّ صَلِّ عَلَى مُحَمَّدٍ وَعَلَى آلِ مُحَمَّدٍ، كَمَا صَلَّيْتَ عَلَى إِبْرَاهِيمَ وَعَلَى آلِ إِبْرَاهِيمَ، إِنَّكَ حَمِيدٌ مَجِيدٌ. اللَّهُمَّ بَارِكْ عَلَى مُحَمَّدٍ وَعَلَى آلِ مُحَمَّدٍ، كَمَا بَارَكْتَ عَلَى إِبْرَاهِيمَ وَعَلَى آلِ إِبْرَاهِيمَ، إِنَّكَ حَمِيدٌ مَجِيدٌ',
    'latin' => 'Allāhumma shalli ‘alā Muhammad, wa ‘alā āli Muhammad, kamā shallaita ‘alā Ibrāhīm, wa ‘alā āli Ibrāhīm. Innaka hamīdun majīd. Allāhumma bārik ‘alā Muhammad, wa ‘alā āli Muhammad, kamā bārakta ‘alā Ibrāhīm, wa ‘alā āli Ibrāhīm. Innaka hamīdun majīd.',
    'arti'  => 'Ya Allah, berilah shalawat kepada Muhammad dan keluarga Muhammad, sebagaimana Engkau memberi shalawat kepada Ibrahim dan keluarganya. Sesungguhnya Engkau Maha Terpuji lagi Maha Mulia. Ya Allah, berilah keberkahan kepada Muhammad dan keluarga Muhammad, sebagaimana Engkau memberi berkah kepada Ibrahim dan keluarganya. Sesungguhnya Engkau Maha Terpuji lagi Maha Mulia.',
  ],
  [
    'judul' => '12. Salam',
    'arab'  => 'السَّلَامُ عَلَيْكُمْ وَرَحْمَةُ اللهِ',
    'latin' => 'Assalāmu ‘alaikum wa rahmatullāh. (menoleh ke kanan kemudian ke kiri)',
    'arti'  => 'Semoga keselamatan dan rahmat Allah terlimpah kepada kalian.',
  ],
];

$SHALAT_RAWATIB = [
  ['waktu'=>'Subuh',   'qabliyah'=>'2 rakaat (muakkad — sangat ditekankan)', 'badiyah'=>'—',                                              'catatan'=>'Sabda Nabi ﷺ: "Dua rakaat fajar lebih baik daripada dunia dan seisinya." (HR. Muslim)'],
  ['waktu'=>'Zhuhur',  'qabliyah'=>'2 atau 4 rakaat (muakkad)',              'badiyah'=>'2 rakaat (muakkad), bisa ditambah 2 rakaat',     'catatan'=>'Total muakkad sekitar Zhuhur: 4 sebelum + 2 sesudah.'],
  ['waktu'=>'Ashar',   'qabliyah'=>'2 atau 4 rakaat (ghairu muakkad)',       'badiyah'=>'—',                                              'catatan'=>'Sunnah ringan sebelum Ashar; tidak ada rawatib sesudahnya.'],
  ['waktu'=>'Maghrib', 'qabliyah'=>'2 rakaat ringan (ghairu muakkad)',       'badiyah'=>'—',                                              'catatan'=>'Setelah Maghrib langsung ke wirid/dzikir; rawatib ba‘diyah Maghrib tidak ditampilkan pada panduan ini.'],
  ['waktu'=>'Isya',    'qabliyah'=>'2 rakaat (ghairu muakkad)',              'badiyah'=>'—',                                              'catatan'=>'Ditutup dengan Witir 1/3 rakaat sebagai penutup malam.'],
];
