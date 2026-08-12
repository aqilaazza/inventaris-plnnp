import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../services/auth_service.dart';
import '../services/api_service.dart';
import 'login_screen.dart';

class ProfilScreen extends StatefulWidget {
  const ProfilScreen({super.key});

  @override
  State<ProfilScreen> createState() => _ProfilScreenState();
}

class _ProfilScreenState extends State<ProfilScreen> {
  String _namaLengkap = '';
  String _username = '';
  String _level = '';
  bool _isLoading = true;

  // Palet warna disamakan dengan RiwayatScreen supaya konsisten di seluruh app
  static const _primary = Color(0xFF4F46E5);
  static const _primaryLight = Color(0xFFEEF2FF);
  static const _red = Color(0xFFEF4444);
  static const _redLight = Color(0xFFFEF2F2);
  static const _bg = Color(0xFFF3F4F6);
  static const _textDark = Color(0xFF111827);
  static const _textGrey = Color(0xFF6B7280);
  static const _textMuted = Color(0xFF9CA3AF);
  static const _border = Color(0xFFE5E7EB);
  static const _divider = Color(0xFFF3F4F6);

  @override
  void initState() {
    super.initState();
    _loadProfil();
  }

  Future<void> _loadProfil() async {
    final user = AuthService.currentUser;
    if (user != null) {
      setState(() {
        _namaLengkap = user.namaLengkap;
        _username = user.username;
        _level = user.level;
      });
    }

    final result = await ApiService.getProfil();
    if (mounted && result['success'] == true) {
      final data = result['data'];
      setState(() {
        _namaLengkap = data['nama_lengkap'] ?? _namaLengkap;
        _username = data['username'] ?? _username;
        _level = data['level'] ?? _level;
        _isLoading = false;
      });
    } else {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _logout() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => Dialog(
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.symmetric(horizontal: 32),
        child: Container(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.1),
                blurRadius: 18,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: _primaryLight,
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.logout_rounded, color: _primary, size: 22),
              ),
              const SizedBox(height: 12),
              Text(
                'Keluar dari Aplikasi?',
                textAlign: TextAlign.center,
                style: GoogleFonts.inter(
                  fontWeight: FontWeight.w800,
                  fontSize: 15,
                  color: _textDark,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Anda perlu login kembali nanti.',
                textAlign: TextAlign.center,
                style: GoogleFonts.inter(
                  fontSize: 12,
                  color: _textGrey,
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: SizedBox(
                      height: 40,
                      child: OutlinedButton(
                        onPressed: () => Navigator.pop(ctx, false),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: _textGrey,
                          side: const BorderSide(color: _border),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        child: Text(
                          'Batal',
                          style: GoogleFonts.inter(fontWeight: FontWeight.w700, fontSize: 13),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: SizedBox(
                      height: 40,
                      child: ElevatedButton(
                        onPressed: () => Navigator.pop(ctx, true),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: _primary,
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          elevation: 0,
                        ),
                        child: Text(
                          'Ya, Logout',
                          style: GoogleFonts.inter(fontWeight: FontWeight.w700, fontSize: 13),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );

    if (confirmed == true) {
      await AuthService.logout();
      if (mounted) {
        Navigator.pushAndRemoveUntil(
          context,
          MaterialPageRoute(builder: (_) => const LoginScreen()),
          (route) => false,
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        title: Text(
          'Inventaris',
          style: GoogleFonts.inter(fontWeight: FontWeight.w800, fontSize: 20),
        ),
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: _primary,
        elevation: 0,
        scrolledUnderElevation: 1,
      ),
      body: _isLoading && _namaLengkap.isEmpty
          ? const Center(child: CircularProgressIndicator(color: _primary))
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  'Profil Saya',
                  style: GoogleFonts.inter(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    color: _textDark,
                  ),
                ),
                const SizedBox(height: 20),

                // Profile Card — gradient disamakan dengan header Ringkasan
                // Aktivitas pada RiwayatScreen
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: _border),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.04),
                        blurRadius: 16,
                        offset: const Offset(0, 6),
                      ),
                    ],
                  ),
                  child: Column(
                    children: [
                      // Avatar
                      Container(
                        width: 80,
                        height: 80,
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: [Color(0xFF312E81), Color(0xFF4C1D95), Color(0xFF6D28D9)],
                          ),
                          borderRadius: BorderRadius.circular(22),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFF4C1D95).withValues(alpha: 0.3),
                              blurRadius: 16,
                              offset: const Offset(0, 6),
                            ),
                          ],
                        ),
                        child: Center(
                          child: Text(
                            _namaLengkap.isNotEmpty ? _namaLengkap[0].toUpperCase() : 'P',
                            style: GoogleFonts.inter(
                              fontSize: 32,
                              fontWeight: FontWeight.w800,
                              color: Colors.white,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        _namaLengkap,
                        style: GoogleFonts.inter(
                          fontSize: 20,
                          fontWeight: FontWeight.w800,
                          color: _textDark,
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 4),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                        decoration: BoxDecoration(
                          color: _primaryLight,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          _level == 'petugas' ? 'Petugas' : _level,
                          style: GoogleFonts.inter(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: _primary,
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),
                      const Divider(color: _divider),
                      const SizedBox(height: 12),

                      // Info items
                      _profileInfoItem(Icons.person_rounded, 'Nama Lengkap', _namaLengkap),
                      const SizedBox(height: 14),
                      _profileInfoItem(Icons.account_circle_rounded, 'Username', _username),
                      const SizedBox(height: 14),
                      _profileInfoItem(Icons.badge_rounded, 'Level Akun', _level == 'petugas' ? 'Petugas' : _level),
                    ],
                  ),
                ),

                const SizedBox(height: 16),

                // App Info Card
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: _border),
                  ),
                  child: Column(
                    children: [
                      _menuItem(Icons.info_outline_rounded, 'Tentang Aplikasi', 'Inventaris Petugas v1.0.0'),
                      const Divider(height: 24, color: _divider),
                      _menuItem(Icons.phone_android_rounded, 'Platform', 'Android & Web'),
                      const Divider(height: 24, color: _divider),
                      _menuItem(Icons.storage_rounded, 'Server', 'MySQL via PHP API'),
                    ],
                  ),
                ),

                const SizedBox(height: 16),

                // Logout Button (versi disetujui — outline merah muda)
                SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton.icon(
                    onPressed: _logout,
                    icon: const Icon(Icons.logout_rounded, size: 20),
                    label: Text(
                      'Logout',
                      style: GoogleFonts.inter(fontWeight: FontWeight.w700, fontSize: 15),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _redLight,
                      foregroundColor: _red,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                        side: const BorderSide(color: Color(0xFFFCA5A5)),
                      ),
                      elevation: 0,
                    ),
                  ),
                ),

                const SizedBox(height: 24),
                Center(
                  child: Text(
                    '© 2026 Sistem Inventaris Barang\nAll rights reserved.',
                    textAlign: TextAlign.center,
                    style: GoogleFonts.inter(fontSize: 11, color: _textMuted),
                  ),
                ),
                const SizedBox(height: 8),
              ],
            ),
    );
  }

  Widget _profileInfoItem(IconData icon, String label, String value) {
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: _bg,
            borderRadius: BorderRadius.circular(11),
          ),
          child: Icon(icon, color: _primary, size: 20),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: GoogleFonts.inter(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: _textMuted,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                style: GoogleFonts.inter(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: _textDark,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _menuItem(IconData icon, String title, String subtitle) {
    return Row(
      children: [
        Icon(icon, color: _textGrey, size: 20),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: GoogleFonts.inter(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: const Color(0xFF374151),
                ),
              ),
              Text(
                subtitle,
                style: GoogleFonts.inter(fontSize: 12, color: _textMuted),
              ),
            ],
          ),
        ),
      ],
    );
  }
}