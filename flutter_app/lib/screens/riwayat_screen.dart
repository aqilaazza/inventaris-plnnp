import 'dart:async';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../services/api_service.dart';
import '../config/api_config.dart';
import '../models/pengecekan.dart';
import 'kalender_riwayat_popup.dart';

class RiwayatScreen extends StatefulWidget {
  const RiwayatScreen({super.key});

  @override
  State<RiwayatScreen> createState() => _RiwayatScreenState();
}

enum _FilterKondisi { semua, baik, rusak, hilang }

extension on _FilterKondisi {
  // Nilai yang dikirim ke API (param 'kondisi')
  String get apiValue {
    switch (this) {
      case _FilterKondisi.baik:
        return 'baik';
      case _FilterKondisi.rusak:
        return 'rusak';
      case _FilterKondisi.hilang:
        return 'hilang';
      case _FilterKondisi.semua:
        return '';
    }
  }
}

class _RiwayatScreenState extends State<RiwayatScreen> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  List<Pengecekan> _items = [];

  // _isInitialLoading: true HANYA saat pertama kali halaman dibuka (belum
  // pernah ada data sama sekali). Dipakai untuk nentuin kapan spinner
  // besar full-screen boleh tampil.
  bool _isInitialLoading = true;

  // _isFetching: true setiap kali sedang ambil data ke API, termasuk saat
  // ganti filter/search/halaman. Dipakai untuk indikator loading yang
  // halus (tanpa bikin seluruh list "ngedip"/reset).
  bool _isFetching = false;

  int _currentPage = 1;
  int _totalPages = 1;
  int _totalItems = 0;

  // Summary dari SELURUH data (bukan cuma halaman ini), dikirim backend
  int _summaryTotal = 0;
  int _summaryBaik = 0;
  int _summaryRusak = 0;
  int _summaryHilang = 0;

  _FilterKondisi _filter = _FilterKondisi.semua;

  static const _primary = Color(0xFF4F46E5);
  static const _primaryLight = Color(0xFFEEF2FF);
  static const _green = Color(0xFF10B981);
  static const _greenLight = Color(0xFFECFDF5);
  static const _orange = Color(0xFFF59E0B);
  static const _red = Color(0xFFEF4444);
  static const _redLight = Color(0xFFFEF2F2);
  static const _bg = Color(0xFFF3F4F6);
  static const _textDark = Color(0xFF111827);
  static const _textGrey = Color(0xFF6B7280);
  static const _textMuted = Color(0xFF9CA3AF);

  @override
  void initState() {
    super.initState();
    _loadRiwayat();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () {
      _loadRiwayat(page: 1);
    });
  }

  void _onFilterChanged(_FilterKondisi value) {
    if (_isFetching || value == _filter) return;
    setState(() => _filter = value);
    _loadRiwayat(page: 1);
  }

  Future<void> _loadRiwayat({int page = 1}) async {
    setState(() => _isFetching = true);

    final result = await ApiService.getRiwayat(
      page: page,
      limit: 20,
      search: _searchController.text.trim(),
      kondisi: _filter.apiValue,
    );

    if (!mounted) return;

    if (result['success'] == true) {
      setState(() {
        _items = result['data'] as List<Pengecekan>;

        final pagination = result['pagination'];
        _currentPage = pagination['page'] ?? 1;
        _totalPages = pagination['total_pages'] ?? 1;
        _totalItems = pagination['total'] ?? 0;

        final summary = result['summary'];
        if (summary != null) {
          _summaryTotal = summary['total'] ?? 0;
          _summaryBaik = summary['total_baik'] ?? 0;
          _summaryRusak = summary['total_rusak'] ?? 0;
          _summaryHilang = summary['total_hilang'] ?? 0;
        }

        _isFetching = false;
        _isInitialLoading = false;
      });
    } else {
      setState(() {
        _isFetching = false;
        _isInitialLoading = false;
      });
    }
  }

  String _relativeDate(String? dateStr) {
    if (dateStr == null || dateStr.isEmpty) return '-';
    try {
      final dt = DateTime.parse(dateStr);
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day);
      final target = DateTime(dt.year, dt.month, dt.day);
      final diff = today.difference(target).inDays;

      if (diff == 0) return DateFormat('HH:mm').format(dt);
      if (diff == 1) return 'Kemarin';
      if (diff < 7) return '$diff Hari Lalu';
      return DateFormat('d MMM yyyy').format(dt);
    } catch (_) {
      return dateStr;
    }
  }

  String _formatDate(String? dateStr) {
    if (dateStr == null || dateStr.isEmpty) return '-';
    try {
      final dt = DateTime.parse(dateStr);
      return DateFormat('d MMM yyyy, HH:mm').format(dt);
    } catch (_) {
      return dateStr;
    }
  }

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

  Color _reviewColor(String status) {
    switch (status) {
      case 'disetujui':
        return _green;
      case 'ditolak':
        return _red;
      default:
        return _orange;
    }
  }

  IconData _reviewIcon(String status) {
    switch (status) {
      case 'disetujui':
        return Icons.check_circle_rounded;
      case 'ditolak':
        return Icons.cancel_rounded;
      default:
        return Icons.hourglass_bottom_rounded;
    }
  }

  @override
  Widget build(BuildContext context) {
    final periodeLabel = DateFormat('MMMM yyyy', 'id_ID').format(DateTime.now());

    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        title: Text(
          'Riwayat',
          style: GoogleFonts.inter(fontWeight: FontWeight.w800, fontSize: 20),
        ),
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: _primary,
        elevation: 0,
        scrolledUnderElevation: 1,
      ),
      body: SafeArea(
        child: _isInitialLoading
            ? const Center(child: CircularProgressIndicator(color: _primary))
            : RefreshIndicator(
                onRefresh: () => _loadRiwayat(page: 1),
                color: _primary,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                  children: [
                    _buildRingkasan(periodeLabel),
                    const SizedBox(height: 16),
                    _buildSearchBar(),
                    const SizedBox(height: 12),
                    _buildFilterChips(),
                    const SizedBox(height: 10),
                    _buildLoadingBar(),
                    const SizedBox(height: 10),
                    _buildAktivitasHeader(),
                    const SizedBox(height: 10),
                    AnimatedSwitcher(
                      duration: const Duration(milliseconds: 220),
                      child: _items.isEmpty
                          ? _buildEmptyState(key: const ValueKey('empty'))
                          : Column(
                              key: const ValueKey('list'),
                              children: _items.map(_buildAktivitasItem).toList(),
                            ),
                    ),
                    const SizedBox(height: 16),
                    _buildPagination(),
                  ],
                ),
              ),
      ),
    );
  }

  /// Garis loading tipis yang muncul/hilang secara halus, dipakai saat
  /// ganti filter/search/halaman. Tidak mengganti konten list yang sudah
  /// tampil, jadi tidak ada efek "ngedip" atau layar reset ke kosong.
  Widget _buildLoadingBar() {
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 200),
      transitionBuilder: (child, anim) => FadeTransition(opacity: anim, child: child),
      child: (_isFetching && !_isInitialLoading)
          ? ClipRRect(
              key: const ValueKey('loading'),
              borderRadius: BorderRadius.circular(4),
              child: const LinearProgressIndicator(
                minHeight: 3,
                backgroundColor: Color(0xFFE5E7EB),
                valueColor: AlwaysStoppedAnimation<Color>(_primary),
              ),
            )
          : const SizedBox(key: ValueKey('idle'), height: 3),
    );
  }

  Widget _buildRingkasan(String periodeLabel) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF312E81), Color(0xFF4C1D95), Color(0xFF6D28D9)],
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF4C1D95).withValues(alpha: 0.3),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.16),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: const Icon(Icons.bar_chart_rounded, color: Colors.white, size: 22),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Ringkasan Aktivitas',
                      style: GoogleFonts.inter(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Periode ${periodeLabel[0].toUpperCase()}${periodeLabel.substring(1)}',
                      style: GoogleFonts.inter(
                        color: Colors.white.withValues(alpha: 0.7),
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              _headerStat('$_summaryTotal', 'Total', Colors.white),
              const SizedBox(width: 8),
              _headerStat('$_summaryBaik', 'Baik', const Color(0xFFA7F3D0)),
              const SizedBox(width: 8),
              _headerStat('$_summaryRusak', 'Rusak', const Color(0xFFFDE68A)),
              const SizedBox(width: 8),
              _headerStat('$_summaryHilang', 'Hilang', const Color(0xFFFCA5A5)),
            ],
          ),
        ],
      ),
    );
  }

  Widget _headerStat(String value, String label, Color valueColor) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 4),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.15),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
        ),
        child: Column(
          children: [
            Text(
              value,
              style: GoogleFonts.inter(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: valueColor,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              textAlign: TextAlign.center,
              style: GoogleFonts.inter(
                fontSize: 9.5,
                fontWeight: FontWeight.w600,
                color: Colors.white.withValues(alpha: 0.8),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSearchBar() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: TextField(
        controller: _searchController,
        style: GoogleFonts.inter(fontSize: 13),
        decoration: InputDecoration(
          hintText: 'Cari Kode Aset atau Lokasi...',
          hintStyle: GoogleFonts.inter(color: _textMuted, fontSize: 13),
          prefixIcon: const Icon(Icons.search_rounded, size: 20, color: _textMuted),
          suffixIcon: _searchController.text.isNotEmpty
              ? IconButton(
                  icon: const Icon(Icons.close_rounded, size: 18),
                  onPressed: () {
                    _debounce?.cancel();
                    _searchController.clear();
                    _loadRiwayat(page: 1);
                  },
                )
              : null,
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        ),
        onChanged: _onSearchChanged,
        onSubmitted: (_) {
          _debounce?.cancel();
          _loadRiwayat(page: 1);
        },
      ),
    );
  }

  Widget _buildFilterChips() {
    Widget chip(String label, _FilterKondisi value) {
      final selected = _filter == value;
      return GestureDetector(
        onTap: () => _onFilterChanged(value),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
          decoration: BoxDecoration(
            color: selected ? _primary : Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: selected ? _primary : const Color(0xFFE5E7EB)),
          ),
          child: Text(
            label,
            style: GoogleFonts.inter(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: selected ? Colors.white : _textGrey,
            ),
          ),
        ),
      );
    }

    return Center(
      child: Wrap(
        alignment: WrapAlignment.center,
        spacing: 8,
        runSpacing: 8,
        children: [
          chip('Semua', _FilterKondisi.semua),
          chip('Baik', _FilterKondisi.baik),
          chip('Rusak', _FilterKondisi.rusak),
          chip('Hilang', _FilterKondisi.hilang),
        ],
      ),
    );
  }

  Widget _buildAktivitasHeader() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          'Aktivitas Terbaru',
          style: GoogleFonts.inter(fontSize: 15, fontWeight: FontWeight.w800, color: _textDark),
        ),
        GestureDetector(
          onTap: () => showKalenderRiwayatPopup(context),
          child: Row(
            children: [
              Text(
                'Lihat Kalender',
                style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w700, color: _primary),
              ),
              const SizedBox(width: 4),
              const Icon(Icons.calendar_month_rounded, size: 15, color: _primary),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildEmptyState({Key? key}) {
    return Padding(
      key: key,
      padding: const EdgeInsets.symmetric(vertical: 40),
      child: Column(
        children: [
          Icon(Icons.search_off_rounded, size: 56, color: Colors.grey[300]),
          const SizedBox(height: 12),
          Text(
            'Belum ada aktivitas',
            style: GoogleFonts.inter(fontSize: 15, fontWeight: FontWeight.w700, color: const Color(0xFF374151)),
          ),
        ],
      ),
    );
  }

  Widget _buildAktivitasItem(Pengecekan item) {
    final kondisiColor = _kondisiColor(item.kondisiTemuan);
    final bermasalah = item.kondisiTemuan != 'Baik' && item.catatan.isNotEmpty;

    return GestureDetector(
      onTap: () => _showDetailDialog(item),
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE5E7EB)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildThumbnail(item, kondisiColor),
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
                          style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w800, color: _primary),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      Text(
                        _relativeDate(item.tglPengecekan),
                        style: GoogleFonts.inter(fontSize: 11, color: _textMuted),
                      ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    item.namaBarang,
                    style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w700, color: _textDark),
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
                          item.namaRuang,
                          style: GoogleFonts.inter(fontSize: 11, color: _textGrey),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  if (bermasalah) ...[
                    const SizedBox(height: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: _red.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.warning_rounded, size: 12, color: _red),
                          const SizedBox(width: 4),
                          Flexible(
                            child: Text(
                              item.catatan,
                              style: GoogleFonts.inter(fontSize: 10, fontWeight: FontWeight.w600, color: _red),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildThumbnail(Pengecekan item, Color kondisiColor) {
    return Stack(
      children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: SizedBox(
            width: 56,
            height: 56,
            child: (item.fotoBukti != null && item.fotoBukti!.isNotEmpty)
                ? CachedNetworkImage(
                    imageUrl: ApiConfig.buktiImageUrl(item.fotoBukti!),
                    fit: BoxFit.cover,
                    placeholder: (_, __) => Container(color: const Color(0xFFE5E7EB)),
                    errorWidget: (_, __, ___) => Container(
                      color: kondisiColor.withValues(alpha: 0.1),
                      child: Icon(Icons.inventory_2_rounded, color: kondisiColor, size: 22),
                    ),
                  )
                : Container(
                    color: kondisiColor.withValues(alpha: 0.1),
                    child: Icon(Icons.inventory_2_rounded, color: kondisiColor, size: 22),
                  ),
          ),
        ),
        Positioned(
          top: 4,
          left: 4,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
            decoration: BoxDecoration(
              color: kondisiColor,
              borderRadius: BorderRadius.circular(5),
            ),
            child: Text(
              item.kondisiTemuan.toUpperCase(),
              style: GoogleFonts.inter(fontSize: 8, fontWeight: FontWeight.w800, color: Colors.white),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildPagination() {
    return Column(
      children: [
        Text(
          'Menampilkan ${_items.length} dari $_totalItems data',
          style: GoogleFonts.inter(fontSize: 11, color: _textMuted),
        ),
        const SizedBox(height: 10),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            OutlinedButton(
              onPressed: (!_isFetching && _currentPage > 1) ? () => _loadRiwayat(page: _currentPage - 1) : null,
              style: OutlinedButton.styleFrom(
                foregroundColor: _primary,
                side: const BorderSide(color: Color(0xFFD1D5DB)),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                padding: const EdgeInsets.symmetric(horizontal: 16),
              ),
              child: Text('Sebelumnya', style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w600)),
            ),
            const SizedBox(width: 12),
            OutlinedButton(
              onPressed: (!_isFetching && _currentPage < _totalPages) ? () => _loadRiwayat(page: _currentPage + 1) : null,
              style: OutlinedButton.styleFrom(
                foregroundColor: _primary,
                side: const BorderSide(color: Color(0xFFD1D5DB)),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                padding: const EdgeInsets.symmetric(horizontal: 16),
              ),
              child: Text('Berikutnya', style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w600)),
            ),
          ],
        ),
      ],
    );
  }

  void _showDetailDialog(Pengecekan item) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (ctx) => DraggableScrollableSheet(
        initialChildSize: 0.65,
        maxChildSize: 0.9,
        minChildSize: 0.4,
        expand: false,
        builder: (_, scrollController) => ListView(
          controller: scrollController,
          padding: const EdgeInsets.all(24),
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
              item.namaBarang,
              style: GoogleFonts.inter(fontSize: 18, fontWeight: FontWeight.w800, color: _textDark),
            ),
            const SizedBox(height: 4),
            Text(
              'Kode: ${item.kodeBarang}',
              style: GoogleFonts.inter(fontSize: 13, color: _textGrey),
            ),
            const SizedBox(height: 16),

            _detailRow('Kategori', item.namaKategori),
            _detailRow('Merk', item.namaMerk),
            _detailRow('Lokasi', '${item.namaUnit} → ${item.namaRuang}'),
            _detailRow('Periode', '${item.namaPeriode} (${item.tahun})'),
            _detailRow('Tanggal Cek', _formatDate(item.tglPengecekan)),

            const SizedBox(height: 10),
            Row(
              children: [
                Text('Temuan: ', style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w600)),
                _buildBadge(item.kondisiTemuan, _kondisiColor(item.kondisiTemuan)),
                const SizedBox(width: 10),
                Text('Review: ', style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w600)),
                _buildReviewBadge(item.statusReview),
              ],
            ),

            if (item.catatan.isNotEmpty) ...[
              const SizedBox(height: 12),
              Text('Catatan:', style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w700, color: const Color(0xFF374151))),
              const SizedBox(height: 4),
              Text(
                '"${item.catatan}"',
                style: GoogleFonts.inter(fontSize: 13, fontStyle: FontStyle.italic, color: _textGrey),
              ),
            ],

            if (item.namaReviewer != null && item.namaReviewer!.isNotEmpty) ...[
              const SizedBox(height: 10),
              Text('Reviewer: ${item.namaReviewer}', style: GoogleFonts.inter(fontSize: 12, color: _textGrey)),
            ],

            if (item.catatanReviewer.isNotEmpty) ...[
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFF3F4F6),
                  borderRadius: BorderRadius.circular(8),
                  border: const Border(left: BorderSide(color: Color(0xFF6B7280), width: 3)),
                ),
                child: Text(
                  '"${item.catatanReviewer}"',
                  style: GoogleFonts.inter(fontSize: 12, fontStyle: FontStyle.italic, color: const Color(0xFF374151)),
                ),
              ),
            ],

            if (item.fotoBukti != null && item.fotoBukti!.isNotEmpty) ...[
              const SizedBox(height: 16),
              Text('Foto Bukti:', style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w700)),
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: CachedNetworkImage(
                  imageUrl: ApiConfig.buktiImageUrl(item.fotoBukti!),
                  placeholder: (_, __) => Container(
                    height: 150,
                    color: const Color(0xFFE5E7EB),
                    child: const Center(child: CircularProgressIndicator(strokeWidth: 2)),
                  ),
                  errorWidget: (_, __, ___) => Container(
                    height: 100,
                    color: const Color(0xFFE5E7EB),
                    child: const Center(child: Icon(Icons.image_not_supported_outlined, color: _textMuted)),
                  ),
                ),
              ),
            ],

            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _buildBadge(String text, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        text,
        style: GoogleFonts.inter(fontSize: 10, fontWeight: FontWeight.w700, color: color),
      ),
    );
  }

  Widget _buildReviewBadge(String status) {
    final color = _reviewColor(status);
    final icon = _reviewIcon(status);
    final label = status == 'disetujui' ? 'Disetujui' : status == 'ditolak' ? 'Ditolak' : 'Menunggu';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 11, color: color),
          const SizedBox(width: 3),
          Text(
            label,
            style: GoogleFonts.inter(fontSize: 10, fontWeight: FontWeight.w700, color: color),
          ),
        ],
      ),
    );
  }

  Widget _detailRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 100,
            child: Text(
              label,
              style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w600, color: _textGrey),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: GoogleFonts.inter(fontSize: 13, color: _textDark),
            ),
          ),
        ],
      ),
    );
  }
}