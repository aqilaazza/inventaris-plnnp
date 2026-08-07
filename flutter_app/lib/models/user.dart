class User {
  final int id;
  final String username;
  final String namaLengkap;
  final String level;
  final String? token;

  User({
    required this.id,
    required this.username,
    required this.namaLengkap,
    required this.level,
    this.token,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] is int ? json['id'] : int.parse(json['id'].toString()),
      username: json['username'] ?? '',
      namaLengkap: json['nama_lengkap'] ?? '',
      level: json['level'] ?? 'petugas',
      token: json['token'],
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'username': username,
    'nama_lengkap': namaLengkap,
    'level': level,
    'token': token,
  };
}
