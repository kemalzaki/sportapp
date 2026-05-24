<?php
/**
 * Data statis Islami (doa, dzikir, hadist, quote, ayat, surah).
 * Bisa dipakai semua halaman.
 */

// 114 nama surah Al-Qur'an + jumlah ayat (untuk daftar offline)
$ISLAMI_SURAH = [
 1=>['Al-Fatihah',7],2=>['Al-Baqarah',286],3=>['Ali Imran',200],4=>['An-Nisa',176],5=>['Al-Maidah',120],
 6=>['Al-An\'am',165],7=>['Al-A\'raf',206],8=>['Al-Anfal',75],9=>['At-Taubah',129],10=>['Yunus',109],
 11=>['Hud',123],12=>['Yusuf',111],13=>['Ar-Ra\'d',43],14=>['Ibrahim',52],15=>['Al-Hijr',99],
 16=>['An-Nahl',128],17=>['Al-Isra',111],18=>['Al-Kahfi',110],19=>['Maryam',98],20=>['Taha',135],
 21=>['Al-Anbiya',112],22=>['Al-Hajj',78],23=>['Al-Mu\'minun',118],24=>['An-Nur',64],25=>['Al-Furqan',77],
 26=>['Asy-Syu\'ara',227],27=>['An-Naml',93],28=>['Al-Qasas',88],29=>['Al-\'Ankabut',69],30=>['Ar-Rum',60],
 31=>['Luqman',34],32=>['As-Sajdah',30],33=>['Al-Ahzab',73],34=>['Saba',54],35=>['Fatir',45],
 36=>['Ya-Sin',83],37=>['As-Saffat',182],38=>['Sad',88],39=>['Az-Zumar',75],40=>['Ghafir',85],
 41=>['Fussilat',54],42=>['Asy-Syura',53],43=>['Az-Zukhruf',89],44=>['Ad-Dukhan',59],45=>['Al-Jasiyah',37],
 46=>['Al-Ahqaf',35],47=>['Muhammad',38],48=>['Al-Fath',29],49=>['Al-Hujurat',18],50=>['Qaf',45],
 51=>['Az-Zariyat',60],52=>['At-Tur',49],53=>['An-Najm',62],54=>['Al-Qamar',55],55=>['Ar-Rahman',78],
 56=>['Al-Waqi\'ah',96],57=>['Al-Hadid',29],58=>['Al-Mujadilah',22],59=>['Al-Hasyr',24],60=>['Al-Mumtahanah',13],
 61=>['As-Saff',14],62=>['Al-Jumu\'ah',11],63=>['Al-Munafiqun',11],64=>['At-Tagabun',18],65=>['At-Talaq',12],
 66=>['At-Tahrim',12],67=>['Al-Mulk',30],68=>['Al-Qalam',52],69=>['Al-Haqqah',52],70=>['Al-Ma\'arij',44],
 71=>['Nuh',28],72=>['Al-Jinn',28],73=>['Al-Muzzammil',20],74=>['Al-Muddassir',56],75=>['Al-Qiyamah',40],
 76=>['Al-Insan',31],77=>['Al-Mursalat',50],78=>['An-Naba',40],79=>['An-Nazi\'at',46],80=>['\'Abasa',42],
 81=>['At-Takwir',29],82=>['Al-Infitar',19],83=>['Al-Mutaffifin',36],84=>['Al-Insyiqaq',25],85=>['Al-Buruj',22],
 86=>['At-Tariq',17],87=>['Al-A\'la',19],88=>['Al-Gasyiyah',26],89=>['Al-Fajr',30],90=>['Al-Balad',20],
 91=>['Asy-Syams',15],92=>['Al-Lail',21],93=>['Ad-Duha',11],94=>['Asy-Syarh',8],95=>['At-Tin',8],
 96=>['Al-\'Alaq',19],97=>['Al-Qadr',5],98=>['Al-Bayyinah',8],99=>['Az-Zalzalah',8],100=>['Al-\'Adiyat',11],
 101=>['Al-Qari\'ah',11],102=>['At-Takasur',8],103=>['Al-\'Asr',3],104=>['Al-Humazah',9],105=>['Al-Fil',5],
 106=>['Quraisy',4],107=>['Al-Ma\'un',7],108=>['Al-Kausar',3],109=>['Al-Kafirun',6],110=>['An-Nasr',3],
 111=>['Al-Lahab',5],112=>['Al-Ikhlas',4],113=>['Al-Falaq',5],114=>['An-Nas',6],
];

// Ayat harian — kumpulan ayat pendek motivasi/kesehatan/disiplin
$ISLAMI_AYAT_HARIAN = [
 ['QS Al-Baqarah: 286','لَا يُكَلِّفُ ٱللَّهُ نَفْسًا إِلَّا وُسْعَهَا','Allah tidak membebani seseorang melainkan sesuai kesanggupannya.'],
 ['QS Al-Insyirah: 6','إِنَّ مَعَ ٱلْعُسْرِ يُسْرًا','Sesungguhnya bersama kesulitan ada kemudahan.'],
 ['QS Ar-Ra\'d: 11','إِنَّ ٱللَّهَ لَا يُغَيِّرُ مَا بِقَوْمٍ حَتَّىٰ يُغَيِّرُوا۟ مَا بِأَنفُسِهِمْ','Allah tidak akan mengubah keadaan suatu kaum sebelum mereka mengubah keadaan diri mereka sendiri.'],
 ['QS Al-A\'raf: 31','وَكُلُوا۟ وَٱشْرَبُوا۟ وَلَا تُسْرِفُوٓا۟','Makan dan minumlah, dan janganlah berlebih-lebihan.'],
 ['QS Al-Baqarah: 168','يَٰٓأَيُّهَا ٱلنَّاسُ كُلُوا۟ مِمَّا فِى ٱلْأَرْضِ حَلَٰلًا طَيِّبًا','Wahai manusia, makanlah yang halal lagi baik dari apa yang ada di bumi.'],
 ['QS Al-Mulk: 2','ٱلَّذِى خَلَقَ ٱلْمَوْتَ وَٱلْحَيَوٰةَ لِيَبْلُوَكُمْ أَيُّكُمْ أَحْسَنُ عَمَلًا','Yang menjadikan mati dan hidup, supaya Dia menguji kamu, siapakah yang lebih baik amalnya.'],
 ['QS Al-\'Asr: 1-3','وَٱلْعَصْرِ، إِنَّ ٱلْإِنسَٰنَ لَفِى خُسْرٍ','Demi masa. Sesungguhnya manusia itu benar-benar berada dalam kerugian.'],
 ['QS Al-Baqarah: 153','يَٰٓأَيُّهَا ٱلَّذِينَ ءَامَنُوا۟ ٱسْتَعِينُوا۟ بِٱلصَّبْرِ وَٱلصَّلَوٰةِ','Hai orang-orang beriman, mohonlah pertolongan dengan sabar dan shalat.'],
 ['QS An-Nahl: 97','مَنْ عَمِلَ صَٰلِحًا مِّن ذَكَرٍ أَوْ أُنثَىٰ وَهُوَ مُؤْمِنٌ فَلَنُحْيِيَنَّهُۥ حَيَوٰةً طَيِّبَةً','Barangsiapa mengerjakan amal saleh, baik laki-laki maupun perempuan dalam keadaan beriman, niscaya akan Kami berikan kepadanya kehidupan yang baik.'],
 ['QS At-Talaq: 3','وَمَن يَتَوَكَّلْ عَلَى ٱللَّهِ فَهُوَ حَسْبُهُۥٓ','Barangsiapa bertawakkal kepada Allah, niscaya Allah akan mencukupkan keperluannya.'],
 ['QS Ali Imran: 159','فَإِذَا عَزَمْتَ فَتَوَكَّلْ عَلَى ٱللَّهِ','Apabila kamu telah membulatkan tekad, bertawakkallah kepada Allah.'],
 ['QS Al-Furqan: 47','وَهُوَ ٱلَّذِى جَعَلَ لَكُمُ ٱلَّيْلَ لِبَاسًا وَٱلنَّوْمَ سُبَاتًا','Dialah yang menjadikan untukmu malam sebagai pakaian dan tidur untuk istirahat.'],
 ['QS Al-Baqarah: 222','إِنَّ ٱللَّهَ يُحِبُّ ٱلتَّوَّٰبِينَ وَيُحِبُّ ٱلْمُتَطَهِّرِينَ','Sesungguhnya Allah menyukai orang-orang yang bertaubat dan menyukai orang-orang yang menyucikan diri.'],
 ['QS Ad-Duha: 4','وَلَلْءَاخِرَةُ خَيْرٌ لَّكَ مِنَ ٱلْأُولَىٰ','Sesungguhnya akhirat itu lebih baik bagimu daripada (kehidupan) dunia.'],
 ['QS Al-Hadid: 7','ءَامِنُوا۟ بِٱللَّهِ وَرَسُولِهِۦ وَأَنفِقُوا۟ مِمَّا جَعَلَكُم مُّسْتَخْلَفِينَ فِيهِ','Berimanlah kepada Allah dan Rasul-Nya dan nafkahkanlah sebagian dari hartamu yang Dia titipkan padamu.'],
];

// Hadist harian (kesehatan & disiplin)
$ISLAMI_HADIST = [
 ['HR Bukhari','Sebaik-baik kalian adalah yang paling bermanfaat bagi manusia.'],
 ['HR Muslim','Tidaklah seorang muslim ditimpa rasa sakit, kepayahan, kesedihan, melainkan Allah hapuskan dengannya dosanya.'],
 ['HR Tirmidzi','Mukmin yang kuat lebih dicintai Allah daripada mukmin yang lemah, dan pada keduanya ada kebaikan.'],
 ['HR Bukhari','Tidaklah anak Adam mengisi wadah yang lebih buruk daripada perutnya. Cukuplah baginya beberapa suap untuk menegakkan tulang punggungnya.'],
 ['HR Ibn Majah','Mintalah kesehatan kepada Allah, karena tidak ada yang diberikan kepada seseorang setelah keyakinan yang lebih baik daripada kesehatan.'],
 ['HR Bukhari','Dua nikmat yang banyak manusia tertipu padanya: kesehatan dan waktu luang.'],
 ['HR Muslim','Kebersihan adalah sebagian dari iman.'],
 ['HR Bukhari','Barangsiapa shalat Subuh maka ia berada dalam jaminan Allah.'],
 ['HR Tirmidzi','Ajarkanlah anak-anakmu memanah, berenang, dan menunggang kuda.'],
 ['HR Abu Dawud','Berjalanlah, karena berjalan itu menyegarkan badan dan menghilangkan kesedihan.'],
 ['HR Bukhari','Seorang muslim adalah yang lisan dan tangannya tidak menyakiti muslim lainnya.'],
 ['HR Muslim','Senyummu kepada saudaramu adalah sedekah.'],
 ['HR Tirmidzi','Tidak akan bergeser kaki anak Adam pada hari kiamat sehingga ditanya tentang umurnya untuk apa dihabiskan.'],
 ['HR Bukhari','Bersegeralah beramal sebelum datang fitnah seperti potongan malam yang gelap gulita.'],
 ['HR Muslim','Sebaik-baik amalan adalah yang dilakukan terus-menerus walau sedikit.'],
];

// Quote islami random saat buka aplikasi
$ISLAMI_QUOTES = [
 ['Imam Syafi\'i','Ilmu itu seperti binatang buruan, dan tulisan adalah pengikatnya.'],
 ['Ali bin Abi Thalib','Jangan jelaskan tentang dirimu kepada siapa pun, karena yang menyukaimu tidak butuh dan yang membencimu tidak percaya.'],
 ['Umar bin Khattab','Hisablah dirimu sebelum kamu dihisab.'],
 ['Hasan Al-Bashri','Wahai anak Adam, sesungguhnya engkau hanyalah kumpulan hari. Setiap hari yang berlalu, berlalu pula sebagian dirimu.'],
 ['Ibnul Qayyim','Hati yang sehat akan menolak makanan yang merusaknya, sebagaimana tubuh yang sehat menolak racun.'],
 ['Imam Ghazali','Tubuh adalah kendaraan bagi jiwa untuk menempuh perjalanan menuju Allah.'],
 ['Ibnu Taimiyyah','Apa yang dilakukan musuhku padaku? Surgaku ada di dadaku, ke mana pun aku pergi ia bersamaku.'],
 ['Ali bin Abi Thalib','Jangan menjadi budak orang lain, padahal Allah menciptakanmu sebagai orang yang merdeka.'],
 ['Imam Syafi\'i','Aku tidak pernah berdebat dengan seseorang, kecuali aku berdoa agar kebenaran muncul dari lisannya.'],
 ['Hasan Al-Bashri','Orang berakal itu menjadikan dunia sebagai jembatan, bukan tempat tinggal.'],
 ['Ibnu Qudamah','Sehat adalah mahkota di kepala orang sehat yang hanya bisa dilihat oleh orang sakit.'],
 ['Umar bin Khattab','Orang yang paling aku cintai adalah yang menunjukkan kekuranganku.'],
];

// Doa harian singkat
$ISLAMI_DOA = [
 ['Doa Bangun Tidur','الْحَمْدُ لِلَّهِ الَّذِي أَحْيَانَا بَعْدَ مَا أَمَاتَنَا وَإِلَيْهِ النُّشُورُ','Segala puji bagi Allah yang menghidupkan kami setelah mematikan kami, dan kepada-Nya kami dibangkitkan.'],
 ['Doa Sebelum Makan','اللَّهُمَّ بَارِكْ لَنَا فِيمَا رَزَقْتَنَا وَقِنَا عَذَابَ النَّارِ','Ya Allah, berkahilah kami dalam rezeki yang Engkau berikan, dan jagalah kami dari siksa neraka.'],
 ['Doa Setelah Makan','الْحَمْدُ لِلَّهِ الَّذِي أَطْعَمَنَا وَسَقَانَا وَجَعَلَنَا مُسْلِمِينَ','Segala puji bagi Allah yang telah memberi kami makan dan minum serta menjadikan kami muslim.'],
 ['Doa Keluar Rumah','بِسْمِ اللَّهِ تَوَكَّلْتُ عَلَى اللَّهِ، وَلَا حَوْلَ وَلَا قُوَّةَ إِلَّا بِاللَّهِ','Dengan nama Allah aku bertawakkal kepada-Nya, tiada daya dan upaya kecuali dengan pertolongan Allah.'],
 ['Doa Masuk Rumah','اللَّهُمَّ إِنِّي أَسْأَلُكَ خَيْرَ الْمَوْلِجِ وَخَيْرَ الْمَخْرَجِ','Ya Allah, sungguh aku memohon kebaikan tempat masuk dan kebaikan tempat keluar.'],
 ['Doa Sebelum Tidur','بِاسْمِكَ اللَّهُمَّ أَمُوتُ وَأَحْيَا','Dengan menyebut nama-Mu ya Allah aku mati dan aku hidup.'],
 ['Doa Naik Kendaraan','سُبْحَانَ الَّذِي سَخَّرَ لَنَا هَذَا وَمَا كُنَّا لَهُ مُقْرِنِينَ','Maha Suci Tuhan yang menundukkan ini bagi kami, sedang kami sebelumnya tidak mampu menguasainya.'],
 ['Doa Sebelum Belajar','رَضِيتُ بِاللَّهِ رَبًّا، وَبِالْإِسْلَامِ دِينًا، وَبِمُحَمَّدٍ نَبِيًّا وَرَسُولًا','Aku ridha Allah sebagai Tuhan, Islam sebagai agama, dan Muhammad sebagai Nabi dan Rasul.'],
 ['Doa Berbuka Puasa','اللَّهُمَّ لَكَ صُمْتُ وَعَلَى رِزْقِكَ أَفْطَرْتُ','Ya Allah, untuk-Mu aku berpuasa, dan dengan rezeki-Mu aku berbuka.'],
 ['Doa Sehat & Kekuatan','اللَّهُمَّ عَافِنِي فِي بَدَنِي، اللَّهُمَّ عَافِنِي فِي سَمْعِي، اللَّهُمَّ عَافِنِي فِي بَصَرِي','Ya Allah, sehatkanlah tubuhku, pendengaranku, dan penglihatanku.'],
];

// Dzikir pagi — [judul, arab lengkap, terjemah/keterangan, transliterasi latin]
$ISLAMI_DZIKIR_PAGI = [
 ['Ayat Kursi',
  'اللَّهُ لَا إِلَٰهَ إِلَّا هُوَ الْحَيُّ الْقَيُّومُ ۚ لَا تَأْخُذُهُ سِنَةٌ وَلَا نَوْمٌ ۚ لَهُ مَا فِي السَّمَاوَاتِ وَمَا فِي الْأَرْضِ ۗ مَنْ ذَا الَّذِي يَشْفَعُ عِنْدَهُ إِلَّا بِإِذْنِهِ ۚ يَعْلَمُ مَا بَيْنَ أَيْدِيهِمْ وَمَا خَلْفَهُمْ ۖ وَلَا يُحِيطُونَ بِشَيْءٍ مِنْ عِلْمِهِ إِلَّا بِمَا شَاءَ ۚ وَسِعَ كُرْسِيُّهُ السَّمَاوَاتِ وَالْأَرْضَ ۖ وَلَا يَئُودُهُ حِفْظُهُمَا ۚ وَهُوَ الْعَلِيُّ الْعَظِيمُ',
  'Dibaca 1×. Allah, tidak ada sesembahan yang berhak disembah kecuali Dia, Yang Hidup kekal lagi terus-menerus mengurus makhluk-Nya. Penjaga dari gangguan setan hingga petang.',
  'Allaahu laa ilaaha illaa huwal hayyul qayyuum, laa ta\'khudzuhuu sinatun wa laa naum, lahuu maa fis-samaawaati wa maa fil-ardh…'],
 ['Tasbih',
  'سُبْحَانَ اللَّهِ وَبِحَمْدِهِ',
  'Dibaca 100×. "Maha Suci Allah, dan dengan memuji-Nya." Menghapus dosa walau sebanyak buih di lautan.',
  'Subhaanallaahi wa bihamdih.'],
 ['Sayyidul Istighfar',
  'اللَّهُمَّ أَنْتَ رَبِّي لَا إِلَٰهَ إِلَّا أَنْتَ، خَلَقْتَنِي وَأَنَا عَبْدُكَ، وَأَنَا عَلَى عَهْدِكَ وَوَعْدِكَ مَا اسْتَطَعْتُ، أَعُوذُ بِكَ مِنْ شَرِّ مَا صَنَعْتُ، أَبُوءُ لَكَ بِنِعْمَتِكَ عَلَيَّ، وَأَبُوءُ بِذَنْبِي فَاغْفِرْ لِي فَإِنَّهُ لَا يَغْفِرُ الذُّنُوبَ إِلَّا أَنْتَ',
  '1×. "Ya Allah, Engkau Rabb-ku, tiada Ilah yang berhak disembah kecuali Engkau. Engkau yang menciptakanku, aku adalah hamba-Mu…" Penghulu istighfar.',
  'Allaahumma anta rabbii laa ilaaha illaa anta, khalaqtanii wa anaa \'abduka…'],
 ['Doa Pagi',
  'أَصْبَحْنَا وَأَصْبَحَ الْمُلْكُ لِلَّهِ، وَالْحَمْدُ لِلَّهِ، لَا إِلَٰهَ إِلَّا اللَّهُ وَحْدَهُ لَا شَرِيكَ لَهُ',
  '1×. "Kami berada di waktu pagi dan kerajaan hanya milik Allah, segala puji bagi Allah." Pengakuan kerajaan milik Allah.',
  'Ashbahnaa wa ashbahal mulku lillaah, walhamdu lillaah…'],
 ['Hasbiyallah',
  'حَسْبِيَ اللَّهُ لَا إِلَٰهَ إِلَّا هُوَ، عَلَيْهِ تَوَكَّلْتُ وَهُوَ رَبُّ الْعَرْشِ الْعَظِيمِ',
  '7×. "Cukuplah Allah bagiku, tidak ada Ilah selain Dia. Kepada-Nya aku bertawakkal dan Dia Rabb \'Arsy yang agung." Mencukupkan urusan dunia & akhirat.',
  'Hasbiyallaahu laa ilaaha illaa huwa, \'alaihi tawakkaltu wa huwa rabbul \'arsyil \'azhiim.'],
 ['Mu\'awwidzat (Al-Ikhlas, Al-Falaq, An-Nas)',
  'قُلْ هُوَ اللَّهُ أَحَدٌ ۚ اللَّهُ الصَّمَدُ ۚ لَمْ يَلِدْ وَلَمْ يُولَدْ ۚ وَلَمْ يَكُنْ لَهُ كُفُوًا أَحَدٌ ۞ قُلْ أَعُوذُ بِرَبِّ الْفَلَقِ ۞ قُلْ أَعُوذُ بِرَبِّ النَّاسِ',
  '3× masing-masing. Pelindung dari segala keburukan makhluk, sihir, dan bisikan setan.',
  'Qul huwallaahu ahad… Qul a\'uudzu birabbil-falaq… Qul a\'uudzu birabbinnaas…'],
];

// Dzikir petang
$ISLAMI_DZIKIR_PETANG = [
 ['Ayat Kursi',
  'اللَّهُ لَا إِلَٰهَ إِلَّا هُوَ الْحَيُّ الْقَيُّومُ ۚ لَا تَأْخُذُهُ سِنَةٌ وَلَا نَوْمٌ ۚ لَهُ مَا فِي السَّمَاوَاتِ وَمَا فِي الْأَرْضِ ۗ مَنْ ذَا الَّذِي يَشْفَعُ عِنْدَهُ إِلَّا بِإِذْنِهِ ۚ يَعْلَمُ مَا بَيْنَ أَيْدِيهِمْ وَمَا خَلْفَهُمْ ۖ وَلَا يُحِيطُونَ بِشَيْءٍ مِنْ عِلْمِهِ إِلَّا بِمَا شَاءَ ۚ وَسِعَ كُرْسِيُّهُ السَّمَاوَاتِ وَالْأَرْضَ ۖ وَلَا يَئُودُهُ حِفْظُهُمَا ۚ وَهُوَ الْعَلِيُّ الْعَظِيمُ',
  'Dibaca 1× setelah Ashar. Penjaga dari gangguan setan hingga pagi.',
  'Allaahu laa ilaaha illaa huwal hayyul qayyuum…'],
 ['Doa Petang',
  'أَمْسَيْنَا وَأَمْسَى الْمُلْكُ لِلَّهِ، وَالْحَمْدُ لِلَّهِ، لَا إِلَٰهَ إِلَّا اللَّهُ وَحْدَهُ لَا شَرِيكَ لَهُ',
  '1×. "Kami berada di waktu petang dan kerajaan hanya milik Allah, segala puji bagi Allah."',
  'Amsainaa wa amsal mulku lillaah, walhamdu lillaah…'],
 ['Tasbih',
  'سُبْحَانَ اللَّهِ وَبِحَمْدِهِ',
  '100×. Menghapus dosa.',
  'Subhaanallaahi wa bihamdih.'],
 ['Sayyidul Istighfar',
  'اللَّهُمَّ أَنْتَ رَبِّي لَا إِلَٰهَ إِلَّا أَنْتَ، خَلَقْتَنِي وَأَنَا عَبْدُكَ، وَأَنَا عَلَى عَهْدِكَ وَوَعْدِكَ مَا اسْتَطَعْتُ، أَعُوذُ بِكَ مِنْ شَرِّ مَا صَنَعْتُ، أَبُوءُ لَكَ بِنِعْمَتِكَ عَلَيَّ، وَأَبُوءُ بِذَنْبِي فَاغْفِرْ لِي فَإِنَّهُ لَا يَغْفِرُ الذُّنُوبَ إِلَّا أَنْتَ',
  '1×. Penghulu istighfar.',
  'Allaahumma anta rabbii laa ilaaha illaa anta…'],
 ['Hasbiyallah',
  'حَسْبِيَ اللَّهُ لَا إِلَٰهَ إِلَّا هُوَ، عَلَيْهِ تَوَكَّلْتُ وَهُوَ رَبُّ الْعَرْشِ الْعَظِيمِ',
  '7×. Mencukupkan dari urusan dunia & akhirat.',
  'Hasbiyallaahu laa ilaaha illaa huwa…'],
 ['Mu\'awwidzat (Al-Ikhlas, Al-Falaq, An-Nas)',
  'قُلْ هُوَ اللَّهُ أَحَدٌ ۞ قُلْ أَعُوذُ بِرَبِّ الْفَلَقِ ۞ قُلْ أَعُوذُ بِرَبِّ النَّاسِ',
  '3× masing-masing. Pelindung dari segala keburukan.',
  'Qul huwallaahu ahad… Qul a\'uudzu birabbil-falaq… Qul a\'uudzu birabbinnaas…'],
];


// Pemilihan harian berdasar tanggal (deterministik biar konsisten sehari)
function islami_pick_today(array $list, string $salt = '') {
    if (empty($list)) return null;
    $seed = (int) date('Yz') + crc32($salt);
    $idx = $seed % count($list);
    return $list[$idx];
}
// Pemilihan random untuk "saat buka aplikasi" (per session)
function islami_pick_session(array $list, string $key) {
    if (empty($list)) return null;
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (!isset($_SESSION['islami_pick'])) $_SESSION['islami_pick'] = [];
    if (!isset($_SESSION['islami_pick'][$key])) {
        $_SESSION['islami_pick'][$key] = random_int(0, count($list)-1);
    }
    return $list[$_SESSION['islami_pick'][$key]];
}
