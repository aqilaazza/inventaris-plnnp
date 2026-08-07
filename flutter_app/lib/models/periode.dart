class Periode {
  final int id;
  final String namaPeriode;
  final String tahun;
  final String tglMulai;
  final String tglSelesai;
  final String status;

  Periode({
    required this.id,
    required this.namaPeriode,
    required this.tahun,
    required this.tglMulai,
    required this.tglSelesai,
    required this.status,
  });

  factory Periode.fromJson(Map<String, dynamic> json) {
    return Periode(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      namaPeriode: json['nama_periode'] ?? '',
      tahun: json['tahun']?.toString() ?? '',
      tglMulai: json['tgl_mulai'] ?? '',
      tglSelesai: json['tgl_selesai'] ?? '',
      status: json['status'] ?? 'aktif',
    );
  }
}
