class Pengecekan {
  final int id;
  final int idBarang;
  final String kodeBarang;
  final String namaBarang;
  final String namaKategori;
  final String namaMerk;
  final String namaUnit;
  final String namaRuang;
  final String namaPeriode;
  final String tahun;
  final String kondisiTemuan;
  final String catatan;
  final String? fotoBukti;
  final String statusReview;
  final String? namaReviewer;
  final String catatanReviewer;
  final String tglPengecekan;
  final String? tglReview;

  Pengecekan({
    required this.id,
    required this.idBarang,
    required this.kodeBarang,
    required this.namaBarang,
    required this.namaKategori,
    required this.namaMerk,
    required this.namaUnit,
    required this.namaRuang,
    required this.namaPeriode,
    required this.tahun,
    required this.kondisiTemuan,
    required this.catatan,
    this.fotoBukti,
    required this.statusReview,
    this.namaReviewer,
    required this.catatanReviewer,
    required this.tglPengecekan,
    this.tglReview,
  });

  factory Pengecekan.fromJson(Map<String, dynamic> json) {
    return Pengecekan(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      idBarang: json['id_barang'] is int ? json['id_barang'] : int.parse(json['id_barang'].toString()),
      kodeBarang: json['kode_barang'] ?? '',
      namaBarang: json['nama_barang'] ?? '',
      namaKategori: json['nama_kategori'] ?? '-',
      namaMerk: json['nama_merk'] ?? '-',
      namaUnit: json['nama_unit'] ?? '-',
      namaRuang: json['nama_ruang'] ?? '-',
      namaPeriode: json['nama_periode'] ?? '',
      tahun: json['tahun']?.toString() ?? '',
      kondisiTemuan: json['kondisi_temuan'] ?? '',
      catatan: json['catatan'] ?? '',
      fotoBukti: json['foto_bukti'],
      statusReview: json['status_review'] ?? 'menunggu',
      namaReviewer: json['nama_reviewer'],
      catatanReviewer: json['catatan_reviewer'] ?? '',
      tglPengecekan: json['tgl_pengecekan'] ?? '',
      tglReview: json['tgl_review'],
    );
  }
}
