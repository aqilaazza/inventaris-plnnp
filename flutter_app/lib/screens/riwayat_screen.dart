import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../services/api_service.dart';
import '../config/api_config.dart';
import '../models/pengecekan.dart';

class RiwayatScreen extends StatefulWidget {
  const RiwayatScreen({super.key});

  @override
  State<RiwayatScreen> createState() => _RiwayatScreenState();
}

class _RiwayatScreenState extends State<RiwayatScreen> {
  final _searchController = TextEditingController();
  List<Pengecekan> _items = [];
  bool _isLoading = true;
  int _currentPage = 1;
  int _totalPages = 1;
  int _totalItems = 0;

  @override
  void initState() {
    super.initState();
    _loadRiwayat();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadRiwayat({int page = 1}) async {
    setState(() => _isLoading = true);

    final result = await ApiService.getRiwayat(
      page: page,
      limit: 20,
      search: _searchController.text.trim(),
    );

    if (!mounted) return;

    if (result['success'] == true) {
      setState(() {
        _items = result['data'] as List<Pengecekan>;
        final pagination = result['pagination'];
        _currentPage = pagination['page'] ?? 1;
        _totalPages = pagination['total_pages'] ?? 1;
        _totalItems = pagination['total'] ?? 0;
        _isLoading = false;
      });
    } else {
      setState(() => _isLoading = false);
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
        return const Color(0xFF10B981);
      case 'Rusak':
        return const Color(0xFFF59E0B);
      case 'Hilang':
        return const Color(0xFFEF4444);
      default:
        return const Color(0xFF6B7280);
    }
  }

  Color _reviewColor(String status) {
    switch (status) {
      case 'disetujui':
        return const Color(0xFF10B981);
      case 'ditolak':
        return const Color(0xFFEF4444);
      default:
        return const Color(0xFFF59E0B);
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
    return Scaffold(
      backgroundColor: const Color(0xFFF3F4F6),
      appBar: AppBar(
        title: Text(
          'Inventaris',
          style: GoogleFonts.inter(fontWeight: FontWeight.w800, fontSize: 20),
        ),
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF4F46E5),
        elevation: 0,
        scrolledUnderElevation: 1,
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: Text(
              'Riwayat Pengecekan Saya',
              style: GoogleFonts.inter(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: const Color(0xFF1F2937),
              ),
            ),
          ),
          const SizedBox(height: 12),

          // Search & Filter Card
          Container(
            margin: const EdgeInsets.symmetric(horizontal: 16),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFE5E7EB)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.03),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.history_rounded, color: Color(0xFF4F46E5), size: 20),
                    const SizedBox(width: 8),
                    Text(
                      'Daftar Pengecekan Barang',
                      style: GoogleFonts.inter(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: const Color(0xFF4F46E5),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _searchController,
                  style: GoogleFonts.inter(fontSize: 13),
                  decoration: InputDecoration(
                    hintText: 'Cari data...',
                    hintStyle: GoogleFonts.inter(color: const Color(0xFF9CA3AF), fontSize: 13),
                    prefixIcon: const Icon(Icons.search_rounded, size: 20, color: Color(0xFF9CA3AF)),
                    suffixIcon: _searchController.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.close_rounded, size: 18),
                            onPressed: () {
                              _searchController.clear();
                              _loadRiwayat();
                            },
                          )
                        : null,
                    filled: true,
                    fillColor: const Color(0xFFF9FAFB),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFFD1D5DB)),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFFD1D5DB)),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFF6366F1), width: 2),
                    ),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  ),
                  onSubmitted: (_) => _loadRiwayat(),
                ),
              ],
            ),
          ),

          const SizedBox(height: 12),

          // List
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: Color(0xFF4F46E5)))
                : _items.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.search_off_rounded, size: 60, color: Colors.grey[300]),
                            const SizedBox(height: 16),
                            Text(
                              'Data Kosong',
                              style: GoogleFonts.inter(
                                fontSize: 18,
                                fontWeight: FontWeight.w700,
                                color: const Color(0xFF374151),
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              'Belum ada riwayat pengecekan barang\nyang Anda lakukan.',
                              textAlign: TextAlign.center,
                              style: GoogleFonts.inter(fontSize: 13, color: const Color(0xFF9CA3AF)),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () => _loadRiwayat(page: 1),
                        color: const Color(0xFF4F46E5),
                        child: ListView.builder(
                          padding: const EdgeInsets.symmetric(horizontal: 16),
                          itemCount: _items.length + 1, // +1 for pagination
                          itemBuilder: (context, index) {
                            if (index == _items.length) {
                              return _buildPagination();
                            }
                            return _buildRiwayatItem(_items[index]);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildRiwayatItem(Pengecekan item) {
    return GestureDetector(
      onTap: () => _showDetailDialog(item),
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE5E7EB)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Status icon
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: _kondisiColor(item.kondisiTemuan).withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                item.kondisiTemuan == 'Baik'
                    ? Icons.check_circle_rounded
                    : item.kondisiTemuan == 'Rusak'
                        ? Icons.warning_rounded
                        : Icons.cancel_rounded,
                color: _kondisiColor(item.kondisiTemuan),
                size: 20,
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
                          item.namaBarang,
                          style: GoogleFonts.inter(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: const Color(0xFF111827),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      Text(
                        item.kodeBarang,
                        style: GoogleFonts.inter(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: const Color(0xFF6B7280),
                          fontFeatures: const [FontFeature.tabularFigures()],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${item.namaKategori} | ${item.namaMerk}',
                    style: GoogleFonts.inter(fontSize: 11, color: const Color(0xFF9CA3AF)),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      _buildBadge(item.kondisiTemuan, _kondisiColor(item.kondisiTemuan)),
                      const SizedBox(width: 6),
                      _buildReviewBadge(item.statusReview),
                      const Spacer(),
                      Text(
                        _formatDate(item.tglPengecekan),
                        style: GoogleFonts.inter(fontSize: 10, color: const Color(0xFF9CA3AF)),
                      ),
                    ],
                  ),
                ],
              ),
            ),
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

  Widget _buildPagination() {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 16),
      child: Column(
        children: [
          Text(
            'Menampilkan ${_items.length} dari $_totalItems data',
            style: GoogleFonts.inter(fontSize: 11, color: const Color(0xFF9CA3AF)),
          ),
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              OutlinedButton(
                onPressed: _currentPage > 1 ? () => _loadRiwayat(page: _currentPage - 1) : null,
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFF4F46E5),
                  side: const BorderSide(color: Color(0xFFD1D5DB)),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                ),
                child: Text('Sebelumnya', style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w600)),
              ),
              const SizedBox(width: 12),
              OutlinedButton(
                onPressed: _currentPage < _totalPages ? () => _loadRiwayat(page: _currentPage + 1) : null,
                style: OutlinedButton.styleFrom(
                  foregroundColor: const Color(0xFF4F46E5),
                  side: const BorderSide(color: Color(0xFFD1D5DB)),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                ),
                child: Text('Berikutnya', style: GoogleFonts.inter(fontSize: 12, fontWeight: FontWeight.w600)),
              ),
            ],
          ),
        ],
      ),
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
              style: GoogleFonts.inter(fontSize: 18, fontWeight: FontWeight.w800, color: const Color(0xFF111827)),
            ),
            const SizedBox(height: 4),
            Text(
              'Kode: ${item.kodeBarang}',
              style: GoogleFonts.inter(fontSize: 13, color: const Color(0xFF6B7280)),
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
                style: GoogleFonts.inter(fontSize: 13, fontStyle: FontStyle.italic, color: const Color(0xFF6B7280)),
              ),
            ],

            if (item.namaReviewer != null && item.namaReviewer!.isNotEmpty) ...[
              const SizedBox(height: 10),
              Text('Reviewer: ${item.namaReviewer}', style: GoogleFonts.inter(fontSize: 12, color: const Color(0xFF6B7280))),
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
                    child: const Center(child: Icon(Icons.image_not_supported_outlined, color: Color(0xFF9CA3AF))),
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
              style: GoogleFonts.inter(fontSize: 13, fontWeight: FontWeight.w600, color: const Color(0xFF6B7280)),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: GoogleFonts.inter(fontSize: 13, color: const Color(0xFF111827)),
            ),
          ),
        ],
      ),
    );
  }
}
