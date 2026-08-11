import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../services/api_service.dart';
import '../config/api_config.dart';
import '../models/pengecekan.dart';

const _primary = Color(0xFF4F46E5);
const _primaryLight = Color(0xFFEEF2FF);
const _green = Color(0xFF10B981);
const _orange = Color(0xFFF59E0B);
const _red = Color(0xFFEF4444);
const _textDark = Color(0xFF111827);
const _textGrey = Color(0xFF6B7280);
const _textMuted = Color(0xFF9CA3AF);

/// Panggil ini untuk menampilkan popup kalender riwayat.
/// Contoh: showKalenderRiwayatPopup(context);
void showKalenderRiwayatPopup(BuildContext context) {
  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (_) => const _KalenderRiwayatPopup(),
  );
}

class _KalenderRiwayatPopup extends StatefulWidget {
  const _KalenderRiwayatPopup();

  @override
  State<_KalenderRiwayatPopup> createState() => _KalenderRiwayatPopupState();
}

class _KalenderRiwayatPopupState extends State<_KalenderRiwayatPopup> {
  bool _isLoading = true;
  String? _errorMessage;

  // Data HANYA untuk bulan yang sedang dibuka, dikelompokkan per tanggal
  // (yyyy-MM-dd). Diambil ulang dari API setiap kali _bulanAktif berubah
  Map<String, List<Pengecekan>> _grouped = {};

  DateTime _bulanAktif = DateTime(DateTime.now().year, DateTime.now().month, 1);
  DateTime _tanggalDipilih = DateTime.now();

  @override
  void initState() {
    super.initState();
    _loadDataUntukBulan(_bulanAktif);
  }

  String _dateKey(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

  Future<void> _loadDataUntukBulan(DateTime bulan) async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    // Hanya minta data bulan+tahun yang sedang aktif ke API, bukan semua
    // riwayat sekaligus. Ini menghindari limit fetch besar (mis. 1000 baris)
    // yang bisa kepotong kalau data sudah menumpuk bertahun-tahun.
    final result = await ApiService.getRiwayatKalender(
      bulan: bulan.month,
      tahun: bulan.year,
    );

    if (!mounted) return;

    if (result['success'] == true) {
      final items = result['data'] as List<Pengecekan>;
      final Map<String, List<Pengecekan>> grouped = {};
      for (final item in items) {
        if (item.tglPengecekan.isEmpty) continue;
        try {
          final dt = DateTime.parse(item.tglPengecekan);
          grouped.putIfAbsent(_dateKey(dt), () => []).add(item);
        } catch (_) {}
      }
      setState(() {
        _grouped = grouped;
        _isLoading = false;
      });
    } else {
      setState(() {
        _grouped = {};
        _isLoading = false;
        _errorMessage = result['message'] ?? 'Gagal memuat data';
      });
    }
  }

  List<Pengecekan> get _aktivitasHariIni => _grouped[_dateKey(_tanggalDipilih)] ?? [];

  Color _kondisiColor(String kondisi) {
    switch (kondisi) {
      case 'Baik':
        return _green;
      case 'Rusak':
        return _orange;
      case 'Hilang':
        return _red;
      default:
        return _textGrey;
    }
  }

  Color _dotColorForDate(DateTime d) {
    final items = _grouped[_dateKey(d)] ?? [];
    if (items.isEmpty) return Colors.transparent;
    final adaMasalah = items.any((e) => e.kondisiTemuan != 'Baik');
    return adaMasalah ? _red : _green;
  }

  void _gantiBulan(int delta) {
    final bulanBaru = DateTime(_bulanAktif.year, _bulanAktif.month + delta, 1);
    setState(() {
      _bulanAktif = bulanBaru;
      // Reset tanggal dipilih ke tanggal 1 di bulan baru (data bulan lama
      // sudah tidak dimuat lagi, jadi tanggal lama tidak relevan lagi).
      _tanggalDipilih = bulanBaru;
    });
    _loadDataUntukBulan(bulanBaru);
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.75,
      maxChildSize: 0.92,
      minChildSize: 0.5,
      expand: false,
      builder: (context, scrollController) {
        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
          ),
          child: ListView(
            controller: scrollController,
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFD1D5DB),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'Kalender Riwayat',
                style: GoogleFonts.inter(fontSize: 17, fontWeight: FontWeight.w800, color: _textDark),
              ),
              const SizedBox(height: 14),
              _buildKalender(),
              const SizedBox(height: 18),
              if (_isLoading)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 32),
                  child: Center(child: CircularProgressIndicator(color: _primary)),
                )
              else if (_errorMessage != null)
                _buildErrorState()
              else
                _buildDaftarAktivitas(),
            ],
          ),
        );
      },
    );
  }

  Widget _buildErrorState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.error_outline_rounded, size: 44, color: Colors.grey[400]),
            const SizedBox(height: 10),
            Text(
              _errorMessage ?? 'Terjadi kesalahan',
              textAlign: TextAlign.center,
              style: GoogleFonts.inter(fontSize: 13, color: _textGrey),
            ),
            const SizedBox(height: 14),
            ElevatedButton(
              onPressed: () => _loadDataUntukBulan(_bulanAktif),
              style: ElevatedButton.styleFrom(backgroundColor: _primary),
              child: Text('Coba Lagi', style: GoogleFonts.inter(color: Colors.white)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildKalender() {
    final namaBulan = DateFormat('MMMM yyyy', 'id_ID').format(_bulanAktif);
    final firstDayOfMonth = DateTime(_bulanAktif.year, _bulanAktif.month, 1);
    final daysInMonth = DateTime(_bulanAktif.year, _bulanAktif.month + 1, 0).day;
    final startOffset = firstDayOfMonth.weekday % 7; // 0 = Minggu
    final totalCell = startOffset + daysInMonth;
    final totalRow = (totalCell / 7).ceil();

    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            IconButton(
              onPressed: _isLoading ? null : () => _gantiBulan(-1),
              icon: const Icon(Icons.chevron_left_rounded, color: _primary),
            ),
            Text(
              '${namaBulan[0].toUpperCase()}${namaBulan.substring(1)}',
              style: GoogleFonts.inter(fontSize: 14, fontWeight: FontWeight.w800, color: _textDark),
            ),
            IconButton(
              onPressed: _isLoading ? null : () => _gantiBulan(1),
              icon: const Icon(Icons.chevron_right_rounded, color: _primary),
            ),
          ],
        ),
        Row(
          children: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab']
              .map((d) => Expanded(
                    child: Center(
                      child: Text(
                        d,
                        style: GoogleFonts.inter(fontSize: 11, fontWeight: FontWeight.w700, color: _textMuted),
                      ),
                    ),
                  ))
              .toList(),
        ),
        const SizedBox(height: 4),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: totalRow * 7,
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 7, childAspectRatio: 1),
          itemBuilder: (context, index) {
            final dayNum = index - startOffset + 1;
            if (dayNum < 1 || dayNum > daysInMonth) return const SizedBox.shrink();

            final date = DateTime(_bulanAktif.year, _bulanAktif.month, dayNum);
            final isSelected = _dateKey(date) == _dateKey(_tanggalDipilih);
            final isToday = _dateKey(date) == _dateKey(DateTime.now());
            final dotColor = _dotColorForDate(date);

            return GestureDetector(
              onTap: () => setState(() => _tanggalDipilih = date),
              child: Container(
                margin: const EdgeInsets.all(2),
                decoration: BoxDecoration(
                  color: isSelected ? _primary : (isToday ? _primaryLight : Colors.transparent),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      '$dayNum',
                      style: GoogleFonts.inter(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: isSelected ? Colors.white : _textDark,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Container(width: 5, height: 5, decoration: BoxDecoration(color: dotColor, shape: BoxShape.circle)),
                  ],
                ),
              ),
            );
          },
        ),
      ],
    );
  }

  Widget _buildDaftarAktivitas() {
    final tanggalLabel = DateFormat('EEEE, d MMMM yyyy', 'id_ID').format(_tanggalDipilih);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          '${tanggalLabel[0].toUpperCase()}${tanggalLabel.substring(1)}',
          style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w800, color: _textDark),
        ),
        const SizedBox(height: 10),
        if (_aktivitasHariIni.isEmpty)
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 28),
            decoration: BoxDecoration(
              color: const Color(0xFFF9FAFB),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Column(
              children: [
                Icon(Icons.event_busy_rounded, size: 36, color: Colors.grey[300]),
                const SizedBox(height: 8),
                Text('Tidak ada aktivitas di tanggal ini', style: GoogleFonts.inter(fontSize: 12, color: _textMuted)),
              ],
            ),
          )
        else
          ..._aktivitasHariIni.map(_buildAktivitasItem),
      ],
    );
  }

  Widget _buildAktivitasItem(Pengecekan item) {
    final kondisiColor = _kondisiColor(item.kondisiTemuan);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: SizedBox(
              width: 44,
              height: 44,
              child: (item.fotoBukti != null && item.fotoBukti!.isNotEmpty)
                  ? CachedNetworkImage(
                      imageUrl: ApiConfig.buktiImageUrl(item.fotoBukti!),
                      fit: BoxFit.cover,
                      placeholder: (_, __) => Container(color: const Color(0xFFE5E7EB)),
                      errorWidget: (_, __, ___) => Container(
                        color: kondisiColor.withValues(alpha: 0.1),
                        child: Icon(Icons.inventory_2_rounded, color: kondisiColor, size: 18),
                      ),
                    )
                  : Container(
                      color: kondisiColor.withValues(alpha: 0.1),
                      child: Icon(Icons.inventory_2_rounded, color: kondisiColor, size: 18),
                    ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.kodeBarang,
                        style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w800, color: _primary),
                      ),
                    ),
                    Text(
                      DateFormat('HH:mm').format(DateTime.tryParse(item.tglPengecekan) ?? DateTime.now()),
                      style: GoogleFonts.inter(fontSize: 11, color: _textMuted),
                    ),
                  ],
                ),
                const SizedBox(height: 2),
                Text(item.namaBarang, style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w700, color: _textDark)),
                const SizedBox(height: 4),
                Row(
                  children: [
                    const Icon(Icons.location_on_rounded, size: 12, color: _textMuted),
                    const SizedBox(width: 3),
                    Expanded(
                      child: Text(
                        item.namaRuang,
                        style: GoogleFonts.inter(fontSize: 11, color: _textGrey),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}