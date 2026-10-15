class ApiConfig {
  /// Base URL aplikasi (server produksi).
  /// Semua platform (Web, Desktop, Android) mengarah ke domain yang sama.
  static String get baseUrl => 'https://inventaris.umumcsr.com';
  
  static String get apiUrl => '$baseUrl/api/api_petugas.php';
  
  // URL untuk gambar barang
  static String barangImageUrl(String filename) => '$baseUrl/uploads/barang/$filename';
  
  // URL untuk foto bukti
  static String buktiImageUrl(String filename) => '$baseUrl/uploads/bukti/$filename';
}
