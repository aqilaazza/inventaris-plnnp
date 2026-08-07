import 'package:flutter_test/flutter_test.dart';
import 'package:inventaris_app/main.dart';

void main() {
  testWidgets('App loads', (WidgetTester tester) async {
    await tester.pumpWidget(
      const InventarisApp(
        isLoggedIn: false,
      ),
    );

    expect(find.byType(InventarisApp), findsOneWidget);
  });
}