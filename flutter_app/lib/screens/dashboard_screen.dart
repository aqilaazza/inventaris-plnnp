import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../config/api_config.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';
import '../models/periode.dart';

const _primary = Color(0xFF4F46E5);
const _primaryDark = Color(0xFF312E81);
const _primaryLight = Color(0xFFEEF2FF);
const _green = Color(0xFF10B981);
const _greenLight = Color(0xFFECFDF5);
const _blue = Color(0xFF2563EB);
const _textDark = Color(0xFF111827);
const _textGrey = Color(0xFF6B7280);
const _textMuted = Color(0xFF9CA3AF);

class DashboardScreen extends StatefulWidget {
  final VoidCallback? onNavigateToScan;
  final VoidCallback? onNavigateToRiwayat;

  const DashboardScreen({
    super.key,
    this.onNavigateToScan,
    this.onNavigateToRiwayat,
  });

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  bool _isLoading = true;
  String? _errorMessage;
  String _namaUser = '';
  String _level = 'petugas';
  Periode? _periodeAktif;
  int _totalCheckedPetugas = 0;
  int _totalBarangAktif = 0;
  List<Map<String, dynamic>> _riwayatTerbaru = [];

  @override
  void initState() {
    super.initState();
    final user = AuthService.currentUser;
    _namaUser = user?.namaLengkap ?? '';
    _level = user?.level ?? 'petugas';
    _loadDashboard();
  }

  Future<void> _loadDashboard() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    Map<String, dynamic> result;
    try {
      result = await ApiService.getDashboard();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _errorMessage = 'Terjadi kesalahan tak terduga: $e';
      });
      return;
    }

    if (!mounted) return;

    if (result['success'] == true) {
      try {
        final data = result['data'];
        setState(() {
          _namaUser = data['user']['nama_lengkap'] ?? AuthService.currentUser?.namaLengkap ?? '';
          _totalCheckedPetugas = data['total_checked_petugas'] ?? 0;
          _totalBarangAktif = data['total_barang_aktif'] ?? 0;

          if (data['periode_aktif'] != null) {
            _periodeAktif = Periode.fromJson(data['periode_aktif']);
          } else {
            _periodeAktif = null;
          }

          _riwayatTerbaru = List<Map<String, dynamic>>.from(data['riwayat_terbaru'] ?? []);
          _isLoading = false;
        });
      } catch (e) {
        setState(() {
          _isLoading = false;
          _errorMessage = 'Gagal memproses data dari server: $e';
        });
      }
    } else {
      setState(() {
        _isLoading = false;
        _errorMessage = result['message'] ?? 'Gagal memuat dashboard.';
      });
    }
  }

  // Progress pribadi petugas untuk kartu utama (numerator konsisten dgn persentase)
  int get _pctPersonal {
    if (_totalBarangAktif <= 0) return 0;
    return ((_totalCheckedPetugas / _totalBarangAktif) * 100).clamp(0, 100).round();
  }

  int? get _sisaHari {
    if (_periodeAktif == null || _periodeAktif!.tglSelesai.isEmpty) return null;
    try {
      final selesai = DateTime.parse(_periodeAktif!.tglSelesai);
      final now = DateTime.now();
      final diff = DateTime(selesai.year, selesai.month, selesai.day)
          .difference(DateTime(now.year, now.month, now.day))
          .inDays;
      return diff;
    } catch (_) {
      return null;
    }
  }

  String _greeting() {
    final hour = DateTime.now().hour;
    if (hour >= 4 && hour < 11) return 'Selamat Pagi,';
    if (hour >= 11 && hour < 15) return 'Selamat Siang,';
    if (hour >= 15 && hour < 18) return 'Selamat Sore,';
    return 'Selamat Malam,';
  }

  String _levelLabel() {
    if (_level == 'petugas') return 'PETUGAS LAPANGAN';
    return _level.toUpperCase();
  }

  String _formatWaktu(String? dateStr) {
    if (dateStr == null || dateStr.isEmpty) return '-';
    try {
      final dt = DateTime.parse(dateStr);
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day);
      final that = DateTime(dt.year, dt.month, dt.day);
      final diffDays = today.difference(that).inDays;
      if (diffDays == 0) return DateFormat('HH:mm').format(dt);
      if (diffDays == 1) return 'Kemarin';
      return DateFormat('dd/MM/yy').format(dt);
    } catch (_) {
      return dateStr;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: SafeArea(
        child: _isLoading
            ? Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const CircularProgressIndicator(color: _primary),
                    const SizedBox(height: 12),
                    Text(
                      'Memuat dashboard...',
                      style: GoogleFonts.inter(fontSize: 13, color: _textGrey),
                    ),
                  ],
                ),
              )
            : _errorMessage != null
                ? _buildErrorState()
                : RefreshIndicator(
                    onRefresh: _loadDashboard,
                    color: _primary,
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
                      children: [
                        _buildHeader(),
                        const SizedBox(height: 22),
                        _buildGreeting(),
                        const SizedBox(height: 20),
                        _buildProgressCard(),
                        const SizedBox(height: 16),
                        _buildScanButton(),
                        const SizedBox(height: 16),
                        _buildStatRow(),
                        const SizedBox(height: 24),
                        _buildRiwayatSection(),
                      ],
                    ),
                  ),
      ),
    );
  }

  Widget _buildErrorState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.error_outline_rounded, size: 48, color: Colors.grey[400]),
            const SizedBox(height: 12),
            Text(
              _errorMessage ?? 'Terjadi kesalahan',
              textAlign: TextAlign.center,
              style: GoogleFonts.inter(fontSize: 13, color: _textGrey),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: _loadDashboard,
              style: ElevatedButton.styleFrom(
                backgroundColor: _primary,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: Text('Coba Lagi', style: GoogleFonts.inter(color: Colors.white, fontWeight: FontWeight.w700)),
            ),
          ],
        ),
      ),
    );
  }

  // ============================================================
  // HEADER
  // ============================================================
  Widget _buildHeader() {
    return Row(
      children: [
        Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFE5E7EB)),
          ),
          child: const Icon(Icons.assignment_rounded, color: _primary, size: 22),
        ),
        const SizedBox(width: 12),
        Text(
          'Dashboard',
          style: GoogleFonts.inter(
            fontSize: 22,
            fontWeight: FontWeight.w800,
            color: _textDark,
          ),
        ),
        const Spacer(),
        CircleAvatar(
          radius: 22,
          backgroundColor: _primary,
          child: Text(
            _namaUser.isNotEmpty ? _namaUser[0].toUpperCase() : 'P',
            style: GoogleFonts.inter(
              color: Colors.white,
              fontWeight: FontWeight.w700,
              fontSize: 16,
            ),
          ),
        ),
      ],
    );
  }

  // ============================================================
  // GREETING
  // ============================================================
  Widget _buildGreeting() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              _greeting(),
              style: GoogleFonts.inter(fontSize: 17, color: _textDark),
            ),
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: _greenLight,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                _levelLabel(),
                style: GoogleFonts.inter(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  color: _green,
                  letterSpacing: 0.3,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 2),
        Text(
          _namaUser.isNotEmpty ? _namaUser : '-',
          style: GoogleFonts.inter(
            fontSize: 22,
            fontWeight: FontWeight.w800,
            color: _primary,
          ),
        ),
        const SizedBox(height: 10),
        Text(
          'Mari selesaikan target pengecekan inventaris hari ini dengan teliti.',
          style: GoogleFonts.inter(fontSize: 13.5, color: _textGrey, height: 1.4),
        ),
      ],
    );
  }

  // ============================================================
  // PROGRESS CARD
  // ============================================================
  Widget _buildProgressCard() {
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [_primary, _primaryDark],
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: _primary.withValues(alpha: 0.3),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Progress Inventarisir',
                style: GoogleFonts.inter(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: Colors.white.withValues(alpha: 0.8),
                ),
              ),
              const Spacer(),
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.inventory_2_rounded, color: Colors.white, size: 20),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            crossAxisAlignment: CrossAxisAlignment.baseline,
            textBaseline: TextBaseline.alphabetic,
            children: [
              Text(
                '$_totalCheckedPetugas',
                style: GoogleFonts.inter(
                  fontSize: 30,
                  fontWeight: FontWeight.w800,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 6),
              Text(
                '/ $_totalBarangAktif Barang',
                style: GoogleFonts.inter(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: Colors.white.withValues(alpha: 0.7),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: _pctPersonal / 100,
              minHeight: 8,
              backgroundColor: Colors.white.withValues(alpha: 0.2),
              valueColor: const AlwaysStoppedAnimation<Color>(_green),
            ),
          ),
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerRight,
            child: Text(
              '$_pctPersonal% Selesai',
              style: GoogleFonts.inter(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: Colors.white.withValues(alpha: 0.85),
              ),
            ),
          ),
        ],
      ),
    );
  }

  // ============================================================
  // SCAN BUTTON
  // ============================================================
  Widget _buildScanButton() {
    return SizedBox(
      width: double.infinity,
      height: 54,
      child: ElevatedButton.icon(
        onPressed: widget.onNavigateToScan,
        icon: const Icon(Icons.qr_code_scanner_rounded, size: 22),
        label: Text(
          'CEK BARANG SEKARANG',
          style: GoogleFonts.inter(
            fontSize: 14,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.5,
          ),
        ),
        style: ElevatedButton.styleFrom(
          backgroundColor: _blue,
          foregroundColor: Colors.white,
          elevation: 0,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
      ),
    );
  }

  // ============================================================
  // PERFORMA + SISA WAKTU
  // ============================================================
  Widget _buildStatRow() {
    final sisaHari = _sisaHari;
    final sudahMulai = _totalCheckedPetugas > 0;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
          child: Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: _primaryLight,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.bolt_rounded, color: _green, size: 18),
                    const SizedBox(width: 6),
                    Text(
                      'Performa',
                      style: GoogleFonts.inter(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: _green,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  sudahMulai ? 'Stabil' : 'Mulai',
                  style: GoogleFonts.inter(
                    fontSize: 19,
                    fontWeight: FontWeight.w800,
                    color: _textDark,
                  ),
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    Container(
                      width: 6,
                      height: 6,
                      decoration: const BoxDecoration(color: _green, shape: BoxShape.circle),
                    ),
                    const SizedBox(width: 5),
                    Text(
                      sudahMulai ? 'SANGAT BAIK' : 'BELUM MULAI',
                      style: GoogleFonts.inter(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: _textGrey,
                        letterSpacing: 0.3,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: _primaryLight,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.access_time_filled_rounded, color: _primary, size: 18),
                    const SizedBox(width: 6),
                    Text(
                      'Sisa Waktu',
                      style: GoogleFonts.inter(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: _primary,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  sisaHari != null && sisaHari >= 0 ? '$sisaHari' : '-',
                  style: GoogleFonts.inter(
                    fontSize: 19,
                    fontWeight: FontWeight.w800,
                    color: _textDark,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  sisaHari == null
                      ? 'TIDAK ADA TUGAS'
                      : (sisaHari >= 0 ? 'HARI TERSISA' : 'TELAH BERAKHIR'),
                  style: GoogleFonts.inter(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    color: _textGrey,
                    letterSpacing: 0.3,
                  ),
                ),
              ],
            ),
          ),
        ),
        ],
      ),
    );
  }

  // ============================================================
  // RIWAYAT SECTION
  // ============================================================
  Widget _buildRiwayatSection() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              'Pengecekan Terakhir',
              style: GoogleFonts.inter(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: _textDark,
              ),
            ),
            if (_riwayatTerbaru.isNotEmpty)
              GestureDetector(
                onTap: widget.onNavigateToRiwayat,
                child: Text(
                  'Lihat Semua',
                  style: GoogleFonts.inter(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: _primary,
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 12),
        if (_riwayatTerbaru.isEmpty)
          _buildEmptyState()
        else
          ..._riwayatTerbaru.map(_buildRiwayatItem),
      ],
    );
  }

  Widget _buildEmptyState() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 40),
      child: Column(
        children: [
          Icon(Icons.assignment_turned_in_rounded, size: 60, color: Colors.grey[300]),
          const SizedBox(height: 14),
          Text(
            'Belum ada aktivitas hari ini',
            style: GoogleFonts.inter(fontSize: 13, color: _textMuted, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }

  Widget _buildRiwayatItem(Map<String, dynamic> rw) {
    final kondisi = rw['kondisi_temuan'] ?? '';
    final isSesuai = kondisi == 'Baik';
    final badgeColor = isSesuai ? _green : _blue;
    final badgeText = isSesuai ? 'SESUAI' : 'UPDATE';
    final foto = rw['foto_barang'];

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: SizedBox(
              width: 48,
              height: 48,
              child: (foto != null && foto.toString().isNotEmpty)
                  ? CachedNetworkImage(
                      imageUrl: ApiConfig.barangImageUrl(foto.toString()),
                      fit: BoxFit.cover,
                      placeholder: (_, __) => Container(color: const Color(0xFFF3F4F6)),
                      errorWidget: (_, __, ___) => Container(
                        color: _primaryLight,
                        child: const Icon(Icons.inventory_2_rounded, color: _primary, size: 20),
                      ),
                    )
                  : Container(
                      color: _primaryLight,
                      child: const Icon(Icons.inventory_2_rounded, color: _primary, size: 20),
                    ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  rw['nama_barang'] ?? '-',
                  style: GoogleFonts.inter(fontSize: 14, fontWeight: FontWeight.w700, color: _textDark),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    const Icon(Icons.location_on_rounded, size: 13, color: _textMuted),
                    const SizedBox(width: 3),
                    Expanded(
                      child: Text(
                        rw['nama_ruang'] ?? '-',
                        style: GoogleFonts.inter(fontSize: 12, color: _textGrey),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                decoration: BoxDecoration(
                  color: badgeColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  badgeText,
                  style: GoogleFonts.inter(fontSize: 10, fontWeight: FontWeight.w800, color: badgeColor),
                ),
              ),
              const SizedBox(height: 6),
              Text(
                _formatWaktu(rw['tgl_pengecekan']),
                style: GoogleFonts.inter(fontSize: 11, color: _textMuted),
              ),
            ],
          ),
        ],
      ),
    );
  }
}