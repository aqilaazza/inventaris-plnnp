import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'services/auth_service.dart';
import 'screens/main_screen.dart';
import 'screens/splash_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  // Initialize Indonesian locale for date formatting
  await initializeDateFormatting('id_ID', null);
  
  // Set status bar style
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.dark,
  ));

  // Check existing login
  final isLoggedIn = await AuthService.init();

  runApp(InventarisApp(isLoggedIn: isLoggedIn));
}

class InventarisApp extends StatelessWidget {
  final bool isLoggedIn;

  const InventarisApp({super.key, required this.isLoggedIn});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Inventaris Petugas',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF4F46E5),
          brightness: Brightness.light,
        ),
        textTheme: GoogleFonts.interTextTheme(
          ThemeData.light().textTheme,
        ),
        scaffoldBackgroundColor: const Color(0xFFF3F4F6),
        appBarTheme: AppBarTheme(
          backgroundColor: Colors.white,
          foregroundColor: const Color(0xFF4F46E5),
          elevation: 0,
          scrolledUnderElevation: 1,
          centerTitle: true,
          titleTextStyle: GoogleFonts.inter(
            fontWeight: FontWeight.w800,
            fontSize: 20,
            color: const Color(0xFF4F46E5),
          ),
        ),
        elevatedButtonTheme: ElevatedButtonThemeData(
          style: ElevatedButton.styleFrom(
            backgroundColor: const Color(0xFF4F46E5),
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(14),
            ),
            elevation: 0,
            textStyle: GoogleFonts.inter(fontWeight: FontWeight.w700),
          ),
        ),
        snackBarTheme: SnackBarThemeData(
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
      home: isLoggedIn ? const MainScreen() : const SplashScreen(),
    );
  }
}