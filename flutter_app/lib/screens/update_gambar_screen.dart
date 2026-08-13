import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../services/api_service.dart';
import '../config/api_config.dart';

class UpdateGambarScreen extends StatefulWidget {
  const UpdateGambarScreen({super.key});

  @override
  State<UpdateGambarScreen> createState() => _UpdateGambarScreenState();
}

class _UpdateGambarScreenState extends State<UpdateGambarScreen> {
  static const Color _primary = Color(0xFF4F46E5);
  static const Color _bg = Color(0xFFF5F6FA);

  static const String _allKey = '__all__';

  List<Map<String, dynamic>> _goodsList = [];
  bool _isLoadingList = true;
  String? _errorMessage;
  String _unitKey = _allKey;
  String _roomKey = _allKey;

  bool _hasFoto(Map<String, dynamic> g) => (g['foto'] ?? '').toString().isNotEmpty;

  int get _totalBarang => _goodsList.length;

  int get _totalAda => _goodsList.where(_hasFoto).length;

  int get _totalBelum => _totalBarang - _totalAda;

  double get _progress => _totalBarang == 0 ? 0 : _totalAda / _totalBarang;

  List<_RoomGroup> get _rooms {
    final map = <String, _RoomGroup>{};
    for (final g in _goodsList) {
      final unit = (g['nama_unit'] ?? '-').toString().trim();
      var ruang = (g['nama_ruang'] ?? '-').toString().trim();
      if (ruang.isEmpty || ruang == '-') ruang = 'Tanpa Ruangan';
      final key = '$unit\x00$ruang';
      final room = map.putIfAbsent(key, () => _RoomGroup(unit: unit, ruang: ruang));
      room.items.add(g);
    }
    final rooms = map.values.toList();
    rooms.sort((a, b) {
      final byUnit = a.unit.compareTo(b.unit);
      return byUnit != 0 ? byUnit : a.ruang.compareTo(b.ruang);
    });
    return rooms;
  }

  List<String> get _units {
    final set = <String>{};
    for (final r in _rooms) {
      set.add(r.unit);
    }
    final list = set.toList()..sort();
    return list;
  }

  List<_RoomGroup> get _roomsForUnit {
    if (_unitKey == _allKey) return _rooms;
    return _rooms.where((r) => r.unit == _unitKey).toList();
  }

  List<_RoomGroup> get _filteredRooms {
    if (_roomKey == _allKey) return _roomsForUnit;
    return _rooms.where((r) => r.key == _roomKey).toList();
  }

  _RoomGroup? get _selectedRoom {
    if (_roomKey == _allKey) return null;
    for (final r in _rooms) {
      if (r.key == _roomKey) return r;
    }
    return null;
  }

  bool get _hasActiveFilter => _unitKey != _allKey || _roomKey != _allKey;

  @override
  void initState() {
    super.initState();
    _loadGoodsList();
  }

  Future<void> _loadGoodsList() async {
    setState(() {
      _isLoadingList = true;
      _errorMessage = null;
    });
    final result = await ApiService.getListBarangAll();
    if (!mounted) return;
    setState(() {
      _isLoadingList = false;
      if (result['success'] == true) {
        _goodsList = List<Map<String, dynamic>>.from(result['data'] ?? []);
      } else {
        _errorMessage = (result['message'] ?? 'Gagal memuat data barang.').toString();
      }
      if (_unitKey != _allKey && !_units.contains(_unitKey)) {
        _unitKey = _allKey;
      }
      if (_roomKey != _allKey && _selectedRoom == null) {
        _roomKey = _allKey;
      }
    });
  }

  void _resetFilter() {
    setState(() {
      _unitKey = _allKey;
      _roomKey = _allKey;
    });
  }

  Future<void> _pickUnit() async {
    final selected = await _FilterPickerSheet.show<String>(
      context,
      title: 'Pilih Unit',
      searchHint: 'Cari unit...',
      allLabel: 'Semua Unit',
      allCount: _rooms.length,
      allIcon: Icons.apps_rounded,
      currentValue: _unitKey,
      items: [
        for (final u in _units)
          _FilterPickerItem(
            value: u,
            label: u,
            count: _rooms.where((r) => r.unit == u).length,
            icon: Icons.business_outlined,
          ),
      ],
    );
    if (selected != null && mounted) {
      setState(() {
        _unitKey = selected;
        // unit changed -> reset room selection kalau tidak valid lagi
        if (_roomKey != _allKey && _selectedRoom?.unit != selected && selected != _allKey) {
          _roomKey = _allKey;
        }
      });
    }
  }

  Future<void> _pickRoom() async {
    final selected = await _FilterPickerSheet.show<String>(
      context,
      title: 'Pilih Ruangan',
      searchHint: 'Cari ruangan...',
      allLabel: 'Semua Ruangan',
      allCount: _roomsForUnit.length,
      allIcon: Icons.apps_rounded,
      currentValue: _roomKey,
      items: [
        for (final r in _roomsForUnit)
          _FilterPickerItem(
            value: r.key,
            label: r.ruang,
            subLabel: r.unit,
            count: r.total,
            icon: Icons.meeting_room_outlined,
            trailingBadge: '${r.adaFoto}/${r.total}',
          ),
      ],
    );
    if (selected != null && mounted) {
      setState(() => _roomKey = selected);
    }
  }

  Future<void> _openRoom(_RoomGroup room) async {
    await Navigator.of(context).push(
      PageRouteBuilder(
        pageBuilder: (_, __, ___) => _RoomDetailScreen(room: room),
        transitionsBuilder: (_, animation, __, child) {
          return FadeTransition(opacity: animation, child: child);
        },
        transitionDuration: const Duration(milliseconds: 220),
      ),
    );
    if (mounted) _loadGoodsList();
  }

  Future<void> _openBarangDetail(Map<String, dynamic> barang) async {
    await Navigator.of(context).push(
      PageRouteBuilder(
        pageBuilder: (_, __, ___) => _GambarDetailScreen(barang: barang),
        transitionsBuilder: (_, animation, __, child) {
          return FadeTransition(opacity: animation, child: child);
        },
        transitionDuration: const Duration(milliseconds: 220),
      ),
    );
    if (mounted) _loadGoodsList();
  }

  Widget _img(String foto) {
    return CachedNetworkImage(
      imageUrl: ApiConfig.barangImageUrl(foto),
      fit: BoxFit.cover,
      placeholder: (_, __) => Container(
        color: const Color(0xFFEDEFF5),
        child: const Center(child: CircularProgressIndicator(strokeWidth: 2, color: _primary)),
      ),
      errorWidget: (_, __, ___) => Container(
        color: const Color(0xFFEDEFF5),
        child: const Center(child: Icon(Icons.broken_image_outlined, color: Color(0xFF9CA3AF), size: 32)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        title: const Text('Galeri Gambar'),
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: _primary,
        elevation: 0,
        scrolledUnderElevation: 1,
      ),
      body: AnimatedSwitcher(
        duration: const Duration(milliseconds: 300),
        child: _isLoadingList
            ? _buildSkeleton(key: const ValueKey('loading'))
            : (_errorMessage != null && _goodsList.isEmpty)
                ? _buildErrorState(key: const ValueKey('error'))
                : RefreshIndicator(
                    key: const ValueKey('content'),
                    onRefresh: _loadGoodsList,
                    color: _primary,
                    child: CustomScrollView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      slivers: [
                        SliverToBoxAdapter(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _buildBanner(),
                              Padding(
                                padding: const EdgeInsets.fromLTRB(16, 18, 16, 12),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Expanded(
                                          child: _FilterField(
                                            icon: Icons.business_outlined,
                                            label: 'Unit',
                                            value: _unitKey == _allKey ? 'Semua Unit' : _unitKey,
                                            active: _unitKey != _allKey,
                                            onTap: _pickUnit,
                                          ),
                                        ),
                                        const SizedBox(width: 10),
                                        Expanded(
                                          child: _FilterField(
                                            icon: Icons.meeting_room_outlined,
                                            label: 'Ruangan',
                                            value: _roomKey == _allKey
                                                ? 'Semua Ruangan'
                                                : (_selectedRoom?.ruang ?? 'Semua Ruangan'),
                                            active: _roomKey != _allKey,
                                            onTap: _pickRoom,
                                          ),
                                        ),
                                      ],
                                    ),
                                    if (_hasActiveFilter) ...[
                                      const SizedBox(height: 10),
                                      GestureDetector(
                                        onTap: _resetFilter,
                                        child: Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: const [
                                            Icon(Icons.filter_alt_off_rounded, size: 14, color: Color(0xFF6B7280)),
                                            SizedBox(width: 4),
                                            Text(
                                              'Reset filter',
                                              style: TextStyle(
                                                fontSize: 12,
                                                fontWeight: FontWeight.w700,
                                                color: Color(0xFF6B7280),
                                                decoration: TextDecoration.underline,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                    const SizedBox(height: 18),
                                    Row(
                                      children: [
                                        Text(
                                          _selectedRoom != null ? 'Foto Barang' : 'Ruangan',
                                          style: const TextStyle(
                                            fontSize: 15,
                                            fontWeight: FontWeight.w800,
                                            color: Color(0xFF111827),
                                          ),
                                        ),
                                        const Spacer(),
                                        Text(
                                          _selectedRoom != null
                                              ? '${_selectedRoom!.total} barang'
                                              : '${_filteredRooms.length} dari ${_rooms.length}',
                                          style: const TextStyle(
                                            fontSize: 12,
                                            fontWeight: FontWeight.w600,
                                            color: Color(0xFF9CA3AF),
                                          ),
                                        ),
                                      ],
                                    ),
                                    const SizedBox(height: 12),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ),
                        if (_goodsList.isEmpty)
                          SliverFillRemaining(hasScrollBody: false, child: _buildEmpty())
                        else if (_selectedRoom != null)
                          _buildSelectedRoomGrid(_selectedRoom!)
                        else if (_filteredRooms.isEmpty)
                          SliverFillRemaining(hasScrollBody: false, child: _buildNoResult())
                        else
                          SliverPadding(
                            padding: const EdgeInsets.symmetric(horizontal: 16),
                            sliver: SliverGrid(
                              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                                crossAxisCount: 2,
                                mainAxisSpacing: 14,
                                crossAxisSpacing: 14,
                                mainAxisExtent: 200,
                              ),
                              delegate: SliverChildBuilderDelegate(
                                (_, index) => _buildRoomCard(_filteredRooms[index], index),
                                childCount: _filteredRooms.length,
                              ),
                            ),
                          ),
                        const SliverToBoxAdapter(child: SizedBox(height: 16)),
                      ],
                    ),
                  ),
      ),
    );
  }

  Widget _buildSkeleton({Key? key}) {
    return ListView(
      key: key,
      physics: const NeverScrollableScrollPhysics(),
      padding: EdgeInsets.zero,
      children: [
        const _PulseBox(height: 150, radius: 0),
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: const [
                  Expanded(child: _PulseBox(height: 50, radius: 14)),
                  SizedBox(width: 10),
                  Expanded(child: _PulseBox(height: 50, radius: 14)),
                ],
              ),
              const SizedBox(height: 18),
              _PulseBox(height: 18, width: 120, radius: 9),
              const SizedBox(height: 14),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Column(
            children: [
              const Row(
                children: [
                  Expanded(child: _PulseBox(height: 200, radius: 18)),
                  SizedBox(width: 14),
                  Expanded(child: _PulseBox(height: 200, radius: 18)),
                ],
              ),
              const SizedBox(height: 14),
              const Row(
                children: [
                  Expanded(child: _PulseBox(height: 200, radius: 18)),
                  SizedBox(width: 14),
                  Expanded(child: _PulseBox(height: 200, radius: 18)),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildErrorState({Key? key}) {
    return Center(
      key: key,
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.wifi_off_rounded, size: 56, color: Color(0xFFC3C8D4)),
            const SizedBox(height: 12),
            const Text(
              'Gagal memuat data',
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Color(0xFF374151)),
            ),
            const SizedBox(height: 6),
            Text(
              _errorMessage ?? 'Terjadi kesalahan. Silakan coba lagi.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
            ),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed: _loadGoodsList,
              icon: const Icon(Icons.refresh_rounded, size: 18),
              label: const Text('Coba Lagi'),
              style: ElevatedButton.styleFrom(
                backgroundColor: _primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBanner() {
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF312E81), Color(0xFF4C1D95), Color(0xFF6D28D9)],
        ),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      child: Stack(
        children: [
          Positioned(
            top: -34,
            right: -24,
            child: Container(
              width: 130,
              height: 130,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.07),
              ),
            ),
          ),
          Positioned(
            bottom: -46,
            right: 70,
            child: Container(
              width: 110,
              height: 110,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.05),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 22),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(9),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.16),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.white.withValues(alpha: 0.24)),
                      ),
                      child: const Icon(Icons.photo_library_rounded, color: Colors.white, size: 22),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Kelengkapan Foto Barang',
                            style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            '$_totalAda dari $_totalBarang barang sudah memiliki foto dokumentasi.',
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.7),
                              fontSize: 11.5,
                              height: 1.35,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: TweenAnimationBuilder<double>(
                    tween: Tween(begin: 0, end: _progress),
                    duration: const Duration(milliseconds: 700),
                    curve: Curves.easeOutCubic,
                    builder: (_, value, __) => LinearProgressIndicator(
                      value: value,
                      minHeight: 7,
                      backgroundColor: Colors.white.withValues(alpha: 0.16),
                      valueColor: const AlwaysStoppedAnimation(Colors.white),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    _bannerStat(_rooms.length.toString(), 'Ruangan', Icons.meeting_room_outlined),
                    const SizedBox(width: 10),
                    _bannerStat(_totalAda.toString(), 'Ada Foto', Icons.check_circle_outline),
                    const SizedBox(width: 10),
                    _bannerStat(_totalBelum.toString(), 'Belum Foto', Icons.image_not_supported_outlined),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _bannerStat(String value, String label, IconData icon) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 11),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.13),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
        ),
        child: Column(
          children: [
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, color: Colors.white, size: 15),
                const SizedBox(width: 6),
                Text(
                  value,
                  style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800),
                ),
              ],
            ),
            const SizedBox(height: 3),
            Text(
              label,
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.75),
                fontSize: 10,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSelectedRoomGrid(_RoomGroup room) {
    return SliverPadding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      sliver: SliverGrid(
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          mainAxisSpacing: 14,
          crossAxisSpacing: 14,
          mainAxisExtent: 200,
        ),
        delegate: SliverChildBuilderDelegate(
          (_, index) => _GambarGridCard(
            barang: room.items[index],
            onTap: () => _openBarangDetail(room.items[index]),
          ),
          childCount: room.items.length,
        ),
      ),
    );
  }

  Widget _buildRoomCard(_RoomGroup room, int index) {
    final rep = room.representativeFoto;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: 300 + (index % 8) * 60),
      curve: Curves.easeOutCubic,
      builder: (_, value, child) => Opacity(
        opacity: value,
        child: Transform.translate(offset: Offset(0, 20 * (1 - value)), child: child),
      ),
      child: _RoomCardTapScale(
        onTap: () => _openRoom(room),
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.06),
                blurRadius: 14,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    if (rep.isNotEmpty)
                      Hero(tag: 'room-${room.key}', child: _img(rep))
                    else
                      Container(
                        color: const Color(0xFFEDEFF5),
                        child: const Center(
                          child: Icon(Icons.meeting_room_outlined, color: Color(0xFFC3C8D4), size: 40),
                        ),
                      ),
                    Positioned(
                      left: 0,
                      right: 0,
                      bottom: 0,
                      height: 62,
                      child: IgnorePointer(
                        child: Container(
                          decoration: const BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topCenter,
                              end: Alignment.bottomCenter,
                              colors: [Colors.transparent, Colors.black38],
                            ),
                          ),
                        ),
                      ),
                    ),
                    Positioned(
                      top: 8,
                      right: 8,
                      child: _badge(
                        '${room.adaFoto}/${room.total}',
                        room.belumFoto == 0 ? const Color(0xFF10B981) : Colors.black.withValues(alpha: 0.42),
                      ),
                    ),
                    Positioned(
                      left: 10,
                      bottom: 8,
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.photo_camera_outlined, color: Colors.white, size: 12),
                          const SizedBox(width: 4),
                          Text(
                            '${room.adaFoto} foto',
                            style: const TextStyle(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w600),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(11),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      room.ruang,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w800, color: Color(0xFF111827)),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      room.unit,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 10.5, fontWeight: FontWeight.w600, color: Color(0xFF6B7280)),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _badge(String text, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white.withValues(alpha: 0.35)),
      ),
      child: Text(
        text,
        style: const TextStyle(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w700),
      ),
    );
  }

  Widget _buildEmpty() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.inventory_2_outlined, size: 56, color: Color(0xFFC3C8D4)),
          const SizedBox(height: 12),
          const Text(
            'Belum ada data barang',
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Color(0xFF374151)),
          ),
          const SizedBox(height: 6),
          Text(
            'Tarik ke bawah untuk memuat ulang.',
            style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
          ),
          const SizedBox(height: 16),
          ElevatedButton.icon(
            onPressed: _loadGoodsList,
            icon: const Icon(Icons.refresh_rounded, size: 18),
            label: const Text('Muat Ulang'),
            style: ElevatedButton.styleFrom(
              backgroundColor: _primary,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNoResult() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.search_off_rounded, size: 56, color: Color(0xFFC3C8D4)),
          const SizedBox(height: 12),
          const Text(
            'Ruangan tidak ditemukan',
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Color(0xFF374151)),
          ),
          const SizedBox(height: 6),
          Text(
            'Coba ubah filter unit atau ruangan.',
            style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: _resetFilter,
            icon: const Icon(Icons.filter_alt_off_rounded, size: 16),
            label: const Text('Reset Filter'),
            style: OutlinedButton.styleFrom(
              foregroundColor: _primary,
              side: const BorderSide(color: Color(0xFFC7D2FE)),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
          ),
        ],
      ),
    );
  }
}

/// Field filter bergaya "pill" yang menampilkan unit/ruangan terpilih
/// dan membuka bottom sheet pencarian saat disentuh.
class _FilterField extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final bool active;
  final VoidCallback onTap;

  const _FilterField({
    required this.icon,
    required this.label,
    required this.value,
    required this.active,
    required this.onTap,
  });

  static const Color _primary = Color(0xFF4F46E5);

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: active ? _primary : const Color(0xFFE5E7EB),
              width: active ? 1.4 : 1,
            ),
            color: active ? const Color(0xFFEEF2FF) : Colors.white,
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: active ? _primary : const Color(0xFFF3F4F6),
                  borderRadius: BorderRadius.circular(9),
                ),
                child: Icon(icon, size: 15, color: active ? Colors.white : const Color(0xFF9CA3AF)),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      label,
                      style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: Color(0xFF9CA3AF)),
                    ),
                    const SizedBox(height: 1),
                    Text(
                      value,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: active ? _primary : const Color(0xFF111827),
                      ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.keyboard_arrow_down_rounded, size: 18, color: active ? _primary : const Color(0xFF9CA3AF)),
            ],
          ),
        ),
      ),
    );
  }
}

class _FilterPickerItem<T> {
  final T value;
  final String label;
  final String? subLabel;
  final int? count;
  final IconData icon;
  final String? trailingBadge;

  _FilterPickerItem({
    required this.value,
    required this.label,
    required this.icon,
    this.subLabel,
    this.count,
    this.trailingBadge,
  });
}

/// Bottom sheet pemilihan filter dengan pencarian, dipakai untuk
/// memilih Unit maupun Ruangan secara konsisten dan lebih "canggih"
/// dibanding dropdown bawaan.
class _FilterPickerSheet<T> extends StatefulWidget {
  final String title;
  final String searchHint;
  final String allLabel;
  final int allCount;
  final IconData allIcon;
  final T currentValue;
  final List<_FilterPickerItem<T>> items;

  const _FilterPickerSheet({
    required this.title,
    required this.searchHint,
    required this.allLabel,
    required this.allCount,
    required this.allIcon,
    required this.currentValue,
    required this.items,
  });

  static const Color _primary = Color(0xFF4F46E5);
  static const String _allKey = '__all__';

  static Future<T?> show<T>(
    BuildContext context, {
    required String title,
    required String searchHint,
    required String allLabel,
    required int allCount,
    required IconData allIcon,
    required T currentValue,
    required List<_FilterPickerItem<T>> items,
  }) {
    return showModalBottomSheet<T>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _FilterPickerSheet<T>(
        title: title,
        searchHint: searchHint,
        allLabel: allLabel,
        allCount: allCount,
        allIcon: allIcon,
        currentValue: currentValue,
        items: items,
      ),
    );
  }

  @override
  State<_FilterPickerSheet<T>> createState() => _FilterPickerSheetState<T>();
}

class _FilterPickerSheetState<T> extends State<_FilterPickerSheet<T>> {
  final TextEditingController _searchController = TextEditingController();
  String _query = '';

  List<_FilterPickerItem<T>> get _filtered {
    final q = _query.toLowerCase().trim();
    if (q.isEmpty) return widget.items;
    return widget.items.where((it) {
      return it.label.toLowerCase().contains(q) || (it.subLabel ?? '').toLowerCase().contains(q);
    }).toList();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final showAll = _query.trim().isEmpty;
    return DraggableScrollableSheet(
      initialChildSize: 0.72,
      minChildSize: 0.4,
      maxChildSize: 0.92,
      expand: false,
      builder: (context, scrollController) {
        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            children: [
              const SizedBox(height: 10),
              Container(
                width: 42,
                height: 4.5,
                decoration: BoxDecoration(
                  color: const Color(0xFFE5E7EB),
                  borderRadius: BorderRadius.circular(4),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        widget.title,
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF111827)),
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close_rounded, size: 20),
                      color: const Color(0xFF9CA3AF),
                      style: IconButton.styleFrom(
                        backgroundColor: const Color(0xFFF3F4F6),
                        padding: const EdgeInsets.all(6),
                      ),
                    ),
                  ],
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: TextField(
                  controller: _searchController,
                  autofocus: false,
                  onChanged: (v) => setState(() => _query = v),
                  style: const TextStyle(fontSize: 14),
                  decoration: InputDecoration(
                    hintText: widget.searchHint,
                    hintStyle: const TextStyle(color: Color(0xFF9CA3AF)),
                    prefixIcon: const Icon(Icons.search_rounded, size: 20, color: Color(0xFF9CA3AF)),
                    suffixIcon: _query.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear_rounded, size: 18),
                            onPressed: () {
                              _searchController.clear();
                              setState(() => _query = '');
                            },
                          )
                        : null,
                    filled: true,
                    fillColor: const Color(0xFFF5F6FA),
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide.none,
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide.none,
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: _FilterPickerSheet._primary, width: 1.4),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              const Divider(height: 1, color: Color(0xFFF0F1F5)),
              Expanded(
                child: (_filtered.isEmpty && !showAll)
                    ? _buildEmptySearch()
                    : ListView(
                        controller: scrollController,
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        children: [
                          if (showAll)
                            _buildTile(
                              icon: widget.allIcon,
                              label: widget.allLabel,
                              subLabel: null,
                              trailing: '${widget.allCount}',
                              selected: widget.currentValue == _FilterPickerSheet._allKey,
                              onTap: () => Navigator.pop(context, _FilterPickerSheet._allKey as T),
                            ),
                          if (showAll && widget.items.isNotEmpty)
                            const Padding(
                              padding: EdgeInsets.fromLTRB(20, 10, 20, 6),
                              child: Divider(height: 1, color: Color(0xFFF0F1F5)),
                            ),
                          for (final item in _filtered)
                            _buildTile(
                              icon: item.icon,
                              label: item.label,
                              subLabel: item.subLabel,
                              trailing: item.trailingBadge ?? (item.count != null ? '${item.count}' : null),
                              selected: widget.currentValue == item.value,
                              onTap: () => Navigator.pop(context, item.value),
                            ),
                        ],
                      ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildEmptySearch() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.search_off_rounded, size: 44, color: Color(0xFFC3C8D4)),
          const SizedBox(height: 10),
          Text(
            'Tidak ditemukan hasil untuk "$_query"',
            style: TextStyle(fontSize: 12.5, color: Colors.grey.shade500),
          ),
        ],
      ),
    );
  }

  Widget _buildTile({
    required IconData icon,
    required String label,
    required String? subLabel,
    required String? trailing,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 11),
        color: selected ? const Color(0xFFEEF2FF) : Colors.transparent,
        child: Row(
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: selected ? _FilterPickerSheet._primary : const Color(0xFFF3F4F6),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 17, color: selected ? Colors.white : const Color(0xFF9CA3AF)),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w700,
                      color: selected ? _FilterPickerSheet._primary : const Color(0xFF111827),
                    ),
                  ),
                  if (subLabel != null && subLabel.isNotEmpty) ...[
                    const SizedBox(height: 2),
                    Text(
                      subLabel,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFF9CA3AF)),
                    ),
                  ],
                ],
              ),
            ),
            if (trailing != null) ...[
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: selected ? _FilterPickerSheet._primary.withValues(alpha: 0.12) : const Color(0xFFF3F4F6),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  trailing,
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: selected ? _FilterPickerSheet._primary : const Color(0xFF6B7280),
                  ),
                ),
              ),
            ],
            if (selected) ...[
              const SizedBox(width: 6),
              const Icon(Icons.check_circle_rounded, size: 18, color: _FilterPickerSheet._primary),
            ],
          ],
        ),
      ),
    );
  }
}

class _RoomGroup {
  final String unit;
  final String ruang;
  final List<Map<String, dynamic>> items;

  _RoomGroup({required this.unit, required this.ruang, List<Map<String, dynamic>>? items})
      : items = items ?? [];

  int get total => items.length;

  String get key => '$unit\x00$ruang';

  int get adaFoto => items.where((g) => (g['foto'] ?? '').toString().isNotEmpty).length;

  int get belumFoto => total - adaFoto;

  String get representativeFoto {
    for (final g in items) {
      final f = (g['foto'] ?? '').toString();
      if (f.isNotEmpty) return f;
    }
    return '';
  }
}

class _PulseBox extends StatefulWidget {
  final double height;
  final double radius;
  final double? width;

  const _PulseBox({required this.height, required this.radius, this.width});

  @override
  State<_PulseBox> createState() => _PulseBoxState();
}

class _PulseBoxState extends State<_PulseBox> with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: Tween(begin: 0.45, end: 1.0).animate(_controller),
      child: Container(
        width: widget.width ?? double.infinity,
        height: widget.height,
        decoration: BoxDecoration(
          color: const Color(0xFFE8EAF1),
          borderRadius: BorderRadius.circular(widget.radius),
        ),
      ),
    );
  }
}

class _RoomCardTapScale extends StatefulWidget {
  final Widget child;
  final VoidCallback onTap;

  const _RoomCardTapScale({required this.child, required this.onTap});

  @override
  State<_RoomCardTapScale> createState() => _RoomCardTapScaleState();
}

class _RoomCardTapScaleState extends State<_RoomCardTapScale> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => setState(() => _pressed = true),
      onTapCancel: () => setState(() => _pressed = false),
      onTapUp: (_) => setState(() => _pressed = false),
      onTap: widget.onTap,
      child: AnimatedScale(
        scale: _pressed ? 0.96 : 1.0,
        duration: const Duration(milliseconds: 120),
        curve: Curves.easeOut,
        child: widget.child,
      ),
    );
  }
}

class _RoomDetailScreen extends StatefulWidget {
  final _RoomGroup room;

  const _RoomDetailScreen({required this.room});

  @override
  State<_RoomDetailScreen> createState() => _RoomDetailScreenState();
}

class _RoomDetailScreenState extends State<_RoomDetailScreen> {
  static const Color _primary = Color(0xFF4F46E5);
  String _search = '';
  String _statusFilter = 'all'; // all | ada | belum

  List<Map<String, dynamic>> get _items => widget.room.items;

  bool _hasFoto(Map<String, dynamic> g) => (g['foto'] ?? '').toString().isNotEmpty;

  List<Map<String, dynamic>> get _filtered {
    final q = _search.toLowerCase().trim();
    return _items.where((g) {
      final matchesQuery = q.isEmpty ||
          (g['nama_barang'] ?? '').toString().toLowerCase().contains(q) ||
          (g['formatted_code'] ?? '').toString().toLowerCase().contains(q);
      final matchesStatus = _statusFilter == 'all' ||
          (_statusFilter == 'ada' && _hasFoto(g)) ||
          (_statusFilter == 'belum' && !_hasFoto(g));
      return matchesQuery && matchesStatus;
    }).toList();
  }

  Future<void> _openDetail(Map<String, dynamic> barang) async {
    await Navigator.of(context).push(
      PageRouteBuilder(
        pageBuilder: (_, __, ___) => _GambarDetailScreen(barang: barang),
        transitionsBuilder: (_, animation, __, child) {
          return FadeTransition(opacity: animation, child: child);
        },
        transitionDuration: const Duration(milliseconds: 220),
      ),
    );
    if (mounted) setState(() {});
  }

  Widget _img(String foto) {
    return CachedNetworkImage(
      imageUrl: ApiConfig.barangImageUrl(foto),
      fit: BoxFit.cover,
      placeholder: (_, __) => const SizedBox.shrink(),
      errorWidget: (_, __, ___) => const SizedBox.shrink(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final room = widget.room;
    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        title: Text(room.ruang),
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: _primary,
        elevation: 0,
        scrolledUnderElevation: 1,
      ),
      body: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildRoomInfo(),
                  const SizedBox(height: 14),
                  _buildSearchField(),
                  const SizedBox(height: 10),
                  _buildStatusChips(),
                  const SizedBox(height: 8),
                ],
              ),
            ),
          ),
          if (_filtered.isEmpty)
            SliverFillRemaining(hasScrollBody: false, child: _buildNoResult())
          else
            SliverPadding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              sliver: SliverGrid(
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: 12,
                  crossAxisSpacing: 12,
                  mainAxisExtent: 200,
                ),
                delegate: SliverChildBuilderDelegate(
                  (_, index) => _GambarGridCard(
                    barang: _filtered[index],
                    onTap: () => _openDetail(_filtered[index]),
                  ),
                  childCount: _filtered.length,
                ),
              ),
            ),
          const SliverToBoxAdapter(child: SizedBox(height: 16)),
        ],
      ),
    );
  }

  Widget _buildRoomInfo() {
    final room = widget.room;
    final rep = room.representativeFoto;
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF4C1D95).withValues(alpha: 0.25),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          if (rep.isNotEmpty)
            Positioned.fill(child: Hero(tag: 'room-${room.key}', child: _img(rep))),
          Positioned.fill(
            child: Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    const Color(0xFF312E81).withValues(alpha: 0.92),
                    const Color(0xFF4C1D95).withValues(alpha: 0.92),
                    const Color(0xFF6D28D9).withValues(alpha: 0.9),
                  ],
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.meeting_room_outlined, color: Colors.white, size: 22),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            room.ruang,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            room.unit,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.75),
                              fontSize: 11.5,
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
                    _roomStat('${room.total}', 'Barang'),
                    const SizedBox(width: 8),
                    _roomStat('${room.adaFoto}', 'Ada Foto'),
                    const SizedBox(width: 8),
                    _roomStat('${room.belumFoto}', 'Belum Foto'),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _roomStat(String value, String label) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.15),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
        ),
        child: Column(
          children: [
            Text(
              value,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Colors.white),
            ),
            const SizedBox(height: 2),
            Text(
              label,
              style: TextStyle(
                fontSize: 10,
                fontWeight: FontWeight.w600,
                color: Colors.white.withValues(alpha: 0.8),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSearchField() {
    return TextField(
      onChanged: (v) => setState(() => _search = v),
      style: const TextStyle(fontSize: 14),
      decoration: InputDecoration(
        hintText: 'Cari nama / kode barang...',
        hintStyle: const TextStyle(color: Color(0xFF9CA3AF)),
        prefixIcon: const Icon(Icons.search_rounded, size: 22, color: Color(0xFF9CA3AF)),
        suffixIcon: _search.isNotEmpty
            ? IconButton(
                icon: const Icon(Icons.clear_rounded, size: 20),
                onPressed: () => setState(() => _search = ''),
              )
            : null,
        filled: true,
        fillColor: Colors.white,
        isDense: true,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: _primary, width: 1.6),
        ),
      ),
    );
  }

  Widget _buildStatusChips() {
    Widget chip(String value, String label, IconData icon) {
      final selected = _statusFilter == value;
      return GestureDetector(
        onTap: () => setState(() => _statusFilter = value),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: selected ? _primary : Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: selected ? _primary : const Color(0xFFE5E7EB)),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 14, color: selected ? Colors.white : const Color(0xFF9CA3AF)),
              const SizedBox(width: 5),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w700,
                  color: selected ? Colors.white : const Color(0xFF6B7280),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          chip('all', 'Semua', Icons.apps_rounded),
          const SizedBox(width: 8),
          chip('ada', 'Ada Foto', Icons.check_circle_outline),
          const SizedBox(width: 8),
          chip('belum', 'Belum Foto', Icons.image_not_supported_outlined),
        ],
      ),
    );
  }

  Widget _buildNoResult() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.image_not_supported_outlined, size: 56, color: Color(0xFFC3C8D4)),
          const SizedBox(height: 12),
          const Text(
            'Barang tidak ditemukan',
            style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Color(0xFF374151)),
          ),
          const SizedBox(height: 6),
          Text(
            'Coba kata kunci atau filter lain.',
            style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
          ),
        ],
      ),
    );
  }
}

class _GambarGridCard extends StatelessWidget {
  final Map<String, dynamic> barang;
  final VoidCallback onTap;

  const _GambarGridCard({required this.barang, required this.onTap});

  String get _foto => (barang['foto'] ?? '').toString();

  bool get _hasFoto => _foto.isNotEmpty;

  Widget _img(String foto) {
    return CachedNetworkImage(
      imageUrl: ApiConfig.barangImageUrl(foto),
      fit: BoxFit.cover,
      placeholder: (_, __) => Container(
        color: const Color(0xFFEDEFF5),
        child: const Center(child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF4F46E5))),
      ),
      errorWidget: (_, __, ___) => Container(
        color: const Color(0xFFEDEFF5),
        child: const Center(child: Icon(Icons.broken_image_outlined, color: Color(0xFF9CA3AF), size: 32)),
      ),
    );
  }

  Widget _chip(String text, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.15), blurRadius: 6, offset: const Offset(0, 2)),
        ],
      ),
      child: Text(
        text,
        style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.white),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final g = barang;
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      clipBehavior: Clip.antiAlias,
      elevation: 0,
      child: InkWell(
        onTap: onTap,
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (_hasFoto)
              Hero(tag: 'photo-${g['id']}', child: _img(_foto))
            else
              Container(
                color: const Color(0xFFEDEFF5),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.image_outlined, color: Color(0xFFC3C8D4), size: 44),
                    const SizedBox(height: 6),
                    Text(
                      'Belum ada foto',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey.shade400,
                      ),
                    ),
                  ],
                ),
              ),
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              height: 70,
              child: IgnorePointer(
                child: Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [Colors.transparent, Colors.black54],
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              top: 8,
              right: 8,
              child: _chip(
                _hasFoto ? 'Ada Foto' : 'Belum Foto',
                _hasFoto ? const Color(0xFF10B981) : const Color(0xFF9CA3AF),
              ),
            ),
            Positioned(
              left: 12,
              right: 12,
              bottom: 10,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    g['nama_barang'] ?? '',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 12.5,
                      fontWeight: FontWeight.w700,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    g['formatted_code'] ?? '',
                    style: const TextStyle(color: Colors.white70, fontSize: 10),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GambarDetailScreen extends StatefulWidget {
  final Map<String, dynamic> barang;

  const _GambarDetailScreen({required this.barang});

  @override
  State<_GambarDetailScreen> createState() => _GambarDetailScreenState();
}

class _GambarDetailScreenState extends State<_GambarDetailScreen> {
  static const Color _primary = Color(0xFF4F46E5);

  Map<String, dynamic> get _barang => widget.barang;

  String get _foto => (_barang['foto'] ?? '').toString();

  bool get _hasFoto => _foto.isNotEmpty;

  bool _isUploading = false;
  File? _newImageFile;

  void _showMessage(String msg, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(msg),
        backgroundColor: isError ? const Color(0xFFEF4444) : const Color(0xFF10B981),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
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

  Future<void> _pickImage(ImageSource source) async {
    try {
      final picked = await ImagePicker().pickImage(source: source, maxWidth: 1600, imageQuality: 85);
      if (picked != null && mounted) {
        setState(() => _newImageFile = File(picked.path));
      }
    } catch (_) {
      if (mounted) {
        _showMessage('Tidak dapat mengakses kamera/galeri.', isError: true);
      }
    }
  }

  Future<void> _uploadImage() async {
    if (_newImageFile == null) {
      _showMessage('Silakan pilih file gambar terlebih dahulu.', isError: true);
      return;
    }
    setState(() => _isUploading = true);

    final result = await ApiService.updateGambarBarang(
      idBarang: _barang['id'],
      subAction: 'upload',
      foto: _newImageFile,
    );

    if (!mounted) return;
    setState(() {
      _isUploading = false;
      if (result['success'] == true) {
        _barang['foto'] = result['foto'];
        _newImageFile = null;
      }
    });

    _showMessage(
      result['success'] == true
          ? (result['message'] ?? 'Gambar berhasil diunggah.')
          : (result['message'] ?? 'Gagal mengunggah gambar.'),
      isError: result['success'] != true,
    );
  }

  Future<void> _deleteImage() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Hapus Gambar?'),
        content: Text('Yakin ingin menghapus foto barang ${_barang['nama_barang']}?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Batal'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Hapus', style: TextStyle(color: Color(0xFFEF4444), fontWeight: FontWeight.w700)),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    setState(() => _isUploading = true);

    final result = await ApiService.updateGambarBarang(
      idBarang: _barang['id'],
      subAction: 'delete',
    );

    if (!mounted) return;
    setState(() {
      _isUploading = false;
      if (result['success'] == true) {
        _barang['foto'] = null;
      }
    });

    _showMessage(
      result['success'] == true
          ? (result['message'] ?? 'Gambar berhasil dihapus.')
          : (result['message'] ?? 'Gagal menghapus gambar.'),
      isError: result['success'] != true,
    );
  }

  void _viewPhoto() {
    if (!_hasFoto) return;
    showDialog(
      context: context,
      builder: (ctx) => Dialog.fullscreen(
        backgroundColor: Colors.black,
        child: Stack(
          children: [
            Positioned.fill(
              child: InteractiveViewer(
                maxScale: 5,
                child: Center(
                  child: CachedNetworkImage(
                    imageUrl: ApiConfig.barangImageUrl(_foto),
                    fit: BoxFit.contain,
                    placeholder: (_, __) => const CircularProgressIndicator(color: Colors.white),
                    errorWidget: (_, __, ___) =>
                        const Icon(Icons.broken_image_outlined, color: Colors.white54, size: 60),
                  ),
                ),
              ),
            ),
            SafeArea(
              child: Align(
                alignment: Alignment.topRight,
                child: Padding(
                  padding: const EdgeInsets.all(8),
                  child: IconButton(
                    onPressed: () => Navigator.pop(ctx),
                    icon: const Icon(Icons.close, color: Colors.white, size: 30),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        title: const Text('Detail Barang'),
        centerTitle: true,
        backgroundColor: Colors.white,
        foregroundColor: _primary,
        elevation: 0,
        scrolledUnderElevation: 1,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _buildPhotoCard(),
          const SizedBox(height: 16),
          _buildInfoCard(),
          const SizedBox(height: 16),
          _buildUploadCard(),
          if (_hasFoto) ...[
            const SizedBox(height: 16),
            _buildDeleteCard(),
          ],
          const SizedBox(height: 16),
        ],
      ),
    );
  }

  Widget _buildPhotoCard() {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: _hasFoto ? const Color(0xFF10B981) : const Color(0xFFE5E7EB),
          width: _hasFoto ? 1.5 : 1,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          SizedBox(
            height: 230,
            width: double.infinity,
            child: _hasFoto
                ? Hero(tag: 'photo-${_barang['id']}', child: _photoImage())
                : Container(
                    color: const Color(0xFFEDEFF5),
                    child: const Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.image_outlined, color: Color(0xFFC3C8D4), size: 56),
                          SizedBox(height: 8),
                          Text(
                            'Barang ini belum memiliki foto',
                            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Color(0xFF9CA3AF)),
                          ),
                        ],
                      ),
                    ),
                  ),
          ),
          Positioned(
            top: 12,
            left: 12,
            child: _chip(
              _hasFoto ? 'Sudah ada foto' : 'Belum ada foto',
              _hasFoto ? const Color(0xFF10B981) : const Color(0xFF9CA3AF),
            ),
          ),
          if (_hasFoto)
            Positioned(
              bottom: 12,
              right: 12,
              child: Material(
                color: Colors.black.withValues(alpha: 0.45),
                shape: const CircleBorder(),
                child: InkWell(
                  onTap: _viewPhoto,
                  customBorder: const CircleBorder(),
                  child: const Padding(
                    padding: EdgeInsets.all(9),
                    child: Icon(Icons.zoom_in_rounded, color: Colors.white, size: 20),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _photoImage() {
    return CachedNetworkImage(
      imageUrl: ApiConfig.barangImageUrl(_foto),
      fit: BoxFit.cover,
      placeholder: (_, __) => Container(
        color: const Color(0xFFEDEFF5),
        child: const Center(child: CircularProgressIndicator(strokeWidth: 2.5, color: _primary)),
      ),
      errorWidget: (_, __, ___) => Container(
        color: const Color(0xFFEDEFF5),
        child: const Center(child: Icon(Icons.broken_image_outlined, color: Color(0xFF9CA3AF), size: 48)),
      ),
    );
  }

  Widget _chip(String text, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.15), blurRadius: 6, offset: const Offset(0, 2)),
        ],
      ),
      child: Text(
        text,
        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Colors.white),
      ),
    );
  }

  Widget _buildInfoCard() {
    final kondisi = (_barang['kondisi'] ?? '-').toString();
    return _card(
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            _barang['nama_barang'] ?? '',
            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF111827)),
          ),
          const SizedBox(height: 2),
          Text(
            _barang['formatted_code'] ?? '',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: _primary),
          ),
          const Divider(height: 24),
          _infoRow(Icons.category_outlined, 'Kategori', (_barang['nama_kategori'] ?? '-').toString()),
          _infoRow(Icons.copyright_outlined, 'Merk', (_barang['nama_merk'] ?? '-').toString()),
          _infoRow(
            Icons.location_on_outlined,
            'Lokasi',
            '${_barang['nama_unit'] ?? '-'} → ${_barang['nama_ruang'] ?? '-'}',
          ),
          _infoRowWidget(
            Icons.build_circle_outlined,
            'Kondisi',
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: _kondisiColor(kondisi).withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                kondisi,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: _kondisiColor(kondisi),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _infoRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: const Color(0xFFEEF2FF),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: _primary, size: 17),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(fontSize: 11, color: Color(0xFF9CA3AF), fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 13,
                    color: Color(0xFF111827),
                    fontWeight: FontWeight.w700,
                    height: 1.3,
                  ),
                  maxLines: 4,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _infoRowWidget(IconData icon, String label, Widget valueWidget) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              color: const Color(0xFFEEF2FF),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: _primary, size: 17),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(fontSize: 11, color: Color(0xFF9CA3AF), fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 4),
                Align(alignment: Alignment.centerLeft, child: valueWidget),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildUploadCard() {
    return _card(
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Unggah / Ganti Foto', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          const Text(
            'Foto akan otomatis dikompres oleh server.',
            style: TextStyle(fontSize: 11, color: Color(0xFF9CA3AF)),
          ),
          const SizedBox(height: 14),
          if (_newImageFile != null) ...[
            Container(
              height: 170,
              width: double.infinity,
              decoration: BoxDecoration(
                border: Border.all(color: _primary, width: 2),
                borderRadius: BorderRadius.circular(12),
              ),
              clipBehavior: Clip.antiAlias,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  Image.file(_newImageFile!, fit: BoxFit.contain),
                  Positioned(
                    top: 8,
                    right: 8,
                    child: InkWell(
                      onTap: () => setState(() => _newImageFile = null),
                      borderRadius: BorderRadius.circular(18),
                      child: const CircleAvatar(
                        radius: 16,
                        backgroundColor: Color(0xFFEF4444),
                        child: Icon(Icons.close_rounded, color: Colors.white, size: 16),
                      ),
                    ),
                  ),
                  Positioned(
                    bottom: 8,
                    left: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: _primary,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Text(
                        'Gambar baru',
                        style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Colors.white),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _pickImage(ImageSource.camera),
                    icon: const Icon(Icons.camera_alt_outlined, size: 18),
                    label: const Text('Kamera'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _primary,
                      side: const BorderSide(color: Color(0xFFC7D2FE)),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => _pickImage(ImageSource.gallery),
                    icon: const Icon(Icons.photo_library_outlined, size: 18),
                    label: const Text('Galeri'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _primary,
                      side: const BorderSide(color: Color(0xFFC7D2FE)),
                    ),
                  ),
                ),
              ],
            ),
          ] else ...[
            Row(
              children: [
                Expanded(
                  child: _sourceButton(
                    Icons.camera_alt_outlined,
                    'Ambil Foto',
                    () => _pickImage(ImageSource.camera),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: _sourceButton(
                    Icons.photo_library_outlined,
                    'Dari Galeri',
                    () => _pickImage(ImageSource.gallery),
                  ),
                ),
              ],
            ),
          ],
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: ElevatedButton.icon(
              onPressed: (_isUploading || _newImageFile == null) ? null : _uploadImage,
              icon: _isUploading
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                    )
                  : const Icon(Icons.cloud_upload_outlined, size: 20),
              label: Text(
                _isUploading ? 'Mengunggah...' : 'Unggah & Kompres',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: _primary,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                elevation: 0,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _sourceButton(IconData icon, String label, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 18),
        decoration: BoxDecoration(
          color: const Color(0xFFF5F3FF),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFC7D2FE)),
        ),
        child: Column(
          children: [
            Icon(icon, color: _primary, size: 26),
            const SizedBox(height: 6),
            Text(
              label,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF374151)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDeleteCard() {
    return _card(
      SizedBox(
        width: double.infinity,
        child: OutlinedButton.icon(
          onPressed: _isUploading ? null : _deleteImage,
          icon: _isUploading
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFEF4444)),
                )
              : const Icon(Icons.delete_outline_rounded, size: 18),
          label: Text(_isUploading ? 'Menghapus...' : 'Hapus Foto Ini'),
          style: OutlinedButton.styleFrom(
            foregroundColor: const Color(0xFFEF4444),
            side: const BorderSide(color: Color(0xFFEF4444)),
            padding: const EdgeInsets.symmetric(vertical: 12),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
        ),
      ),
    );
  }

  Widget _card(Widget child) {
    return Container(
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
      child: child,
    );
  }
}