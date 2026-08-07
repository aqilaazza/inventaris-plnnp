class Barang {
  final int id;
  final String namaBarang;
  final String formattedCode;
  final String namaMerk;
  final String namaJenis;
  final String namaKategori;
  final String namaUnit;
  final String namaRuang;
  final String kondisi;
  final String statusAktif;
  final String? foto;
  final int jumlah;
  final String? tglPembelian;

  Barang({
    required this.id,
    required this.namaBarang,
    required this.formattedCode,
    required this.namaMerk,
    required this.namaJenis,
    required this.namaKategori,
    required this.namaUnit,
    required this.namaRuang,
    required this.kondisi,
    required this.statusAktif,
    this.foto,
    required this.jumlah,
    this.tglPembelian,
  });

  factory Barang.fromJson(Map<String, dynamic> json) {
    return Barang(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      namaBarang: json['nama_barang'] ?? '',
      formattedCode: json['formatted_code'] ?? '',
      namaMerk: json['nama_merk'] ?? '-',
      namaJenis: json['nama_jenis'] ?? '-',
      namaKategori: json['nama_kategori'] ?? '-',
      namaUnit: json['nama_unit'] ?? '-',
      namaRuang: json['nama_ruang'] ?? '-',
      kondisi: json['kondisi'] ?? 'Baik',
      statusAktif: json['status_aktif'] ?? 'aktif',
      foto: json['foto'],
      jumlah: json['jumlah'] is int ? json['jumlah'] : int.tryParse(json['jumlah']?.toString() ?? '1') ?? 1,
      tglPembelian: json['tgl_pembelian'],
    );
  }
}
