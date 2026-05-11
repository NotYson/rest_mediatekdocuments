; =============================================================================
; MediaTekDocuments.iss — Script d'installation Inno Setup 6
; Application : MediaTekDocuments (client C# .NET Framework 4.7.2)
; =============================================================================
;
; PRÉREQUIS AVANT COMPILATION
; ─────────────────────────────────────────────────────────────────────────────
; 1. Inno Setup 6 installé : https://jrsoftware.org/isdl.php
;
; 2. Modifier Access.cs dans le projet C# pour lire l'URL depuis App.config
;    au lieu de la valeur codée en dur :
;
;      // AVANT (MediaTekDocuments/dal/Access.cs, ~ligne 20) :
;      private static readonly string urlApi =
;          "http://localhost/rest_mediatekdocuments/";
;
;      // APRÈS :
;      private static readonly string urlApi =
;          System.Configuration.ConfigurationManager
;              .AppSettings["urlApi"]
;              ?? "http://localhost/rest_mediatekdocuments/";
;
;    Ajouter aussi dans App.config, dans <configuration> :
;      <appSettings>
;        <add key="urlApi" value="http://localhost/rest_mediatekdocuments/" />
;      </appSettings>
;
; 3. Compiler le projet en Release (Rebuild → Release|AnyCPU)
;
; 4. Mettre à jour SourceDir ci-dessous avec le chemin du dossier Release
; =============================================================================

; ── À ADAPTER ────────────────────────────────────────────────────────────────
#define SourceDir "C:\chemin\vers\MediaTekDocuments\bin\Release"
; ─────────────────────────────────────────────────────────────────────────────

#define AppName       "MediaTekDocuments"
#define AppVersion    "1.0"
#define AppPublisher  "Mediatek86"
#define AppExeName    "MediaTekDocuments.exe"
#define AppConfigName "MediaTekDocuments.exe.config"
#define AppUrlKey     "urlApi"

[Setup]
AppId={{F3C2A1D0-B4E5-4F67-89AB-CD1234EF5678}
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher={#AppPublisher}
AppSupportURL=https://github.com/NotYson/rest_mediatekdocuments
DefaultDirName={autopf}\{#AppName}
DefaultGroupName={#AppName}
AllowNoIcons=yes
OutputDir=output
OutputBaseFilename=MediaTekDocuments_Setup_{#AppVersion}
SetupIconFile=
Compression=lzma2/ultra64
SolidCompression=yes
WizardStyle=modern
MinVersion=10.0
UninstallDisplayIcon={app}\{#AppExeName}
DisableWelcomePage=no

[Languages]
Name: "french"; MessagesFile: "compiler:Languages\French.isl"

[Tasks]
Name: "desktopicon"; Description: "Créer une icône sur le &Bureau"; GroupDescription: "Icônes supplémentaires :"; Flags: unchecked

[Files]
Source: "{#SourceDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\{#AppName}";               Filename: "{app}\{#AppExeName}"
Name: "{group}\Désinstaller {#AppName}";  Filename: "{uninstallexe}"
Name: "{commondesktop}\{#AppName}";       Filename: "{app}\{#AppExeName}"; Tasks: desktopicon

[Run]
Filename: "{app}\{#AppExeName}"; Description: "Lancer {#AppName} maintenant"; Flags: nowait postinstall skipifsilent

; =============================================================================
[Code]
; =============================================================================

var
  ApiUrlPage: TInputQueryWizardPage;

// ─────────────────────────────────────────────────────────────────────────────
// Vérification de .NET Framework 4.7.2
// (clé de registre Release >= 461808)
// ─────────────────────────────────────────────────────────────────────────────
function IsDotNet472Installed: Boolean;
var
  Release: Cardinal;
begin
  Result := RegQueryDWordValue(
    HKLM,
    'SOFTWARE\Microsoft\NET Framework Setup\NDP\v4\Full',
    'Release',
    Release
  ) and (Release >= 461808);
end;

function InitializeSetup: Boolean;
begin
  Result := True;
  if not IsDotNet472Installed then
  begin
    if MsgBox(
      '.NET Framework 4.7.2 ou supérieur est requis pour exécuter ' +
      '{#AppName}.' + #13#10#13#10 +
      'Téléchargez-le sur :' + #13#10 +
      'https://dotnet.microsoft.com/download/dotnet-framework' + #13#10#13#10 +
      'Continuer quand même ?',
      mbConfirmation,
      MB_YESNO
    ) = IDNO then
      Result := False;
  end;
end;

// ─────────────────────────────────────────────────────────────────────────────
// Page personnalisée : saisie de l'URL de l'API
// ─────────────────────────────────────────────────────────────────────────────
procedure InitializeWizard;
begin
  ApiUrlPage := CreateInputQueryPage(
    wpSelectDir,
    'Configuration du serveur',
    'Adresse de l''API REST Mediatek86',
    'Entrez l''URL complète de l''API REST déployée sur votre serveur.' + #13#10 +
    'Exemple : http://192.168.1.10/rest_mediatekdocuments/'
  );
  ApiUrlPage.Add('URL de l''API :', False);
  ApiUrlPage.Values[0] := 'http://localhost/rest_mediatekdocuments/';
end;

function NextButtonClick(CurPageID: Integer): Boolean;
var
  Url: String;
begin
  Result := True;
  if CurPageID <> ApiUrlPage.ID then
    Exit;

  Url := Trim(ApiUrlPage.Values[0]);

  if Url = '' then
  begin
    MsgBox('L''URL de l''API est obligatoire.', mbError, MB_OK);
    Result := False;
    Exit;
  end;

  if (Pos('http://', Url) <> 1) and (Pos('https://', Url) <> 1) then
  begin
    MsgBox(
      'L''URL doit commencer par http:// ou https://',
      mbError,
      MB_OK
    );
    Result := False;
    Exit;
  end;

  // S'assurer que l'URL se termine par '/'
  if Url[Length(Url)] <> '/' then
    ApiUrlPage.Values[0] := Url + '/';
end;

// ─────────────────────────────────────────────────────────────────────────────
// Écriture de l'URL dans MediaTekDocuments.exe.config
//
// Stratégie :
//   1. Si la clé urlApi existe déjà → ne rien faire (mise à jour safe)
//   2. Si <appSettings> existe      → insérer la clé après l'ouverture
//   3. Sinon                         → insérer un bloc <appSettings> complet
//                                      avant </configuration>
// ─────────────────────────────────────────────────────────────────────────────
procedure WriteApiUrlToConfig(ApiUrl: String);
var
  ConfigPath : String;
  RawContent : AnsiString;
  Content    : String;
  KeyLine    : String;
  InsertPos  : Integer;
  Before, After : String;
begin
  ConfigPath := ExpandConstant('{app}\{#AppConfigName}');

  if not LoadStringFromFile(ConfigPath, RawContent) then
  begin
    Log('AVERTISSEMENT : ' + ConfigPath + ' introuvable — configuration ignorée.');
    Exit;
  end;

  Content := String(RawContent);
  KeyLine := '    <add key="{#AppUrlKey}" value="' + ApiUrl + '" />';

  // Cas 1 : clé déjà présente (mise à jour ou réinstallation)
  if Pos('key="{#AppUrlKey}"', Content) > 0 then
  begin
    Log('Clé {#AppUrlKey} déjà présente dans ' + ConfigPath + ' — aucune modification.');
    Exit;
  end;

  // Cas 2 : <appSettings> existe, on insère la clé après la balise ouvrante
  InsertPos := Pos('<appSettings>', Content);
  if InsertPos > 0 then
  begin
    InsertPos := InsertPos + Length('<appSettings>');
    Before := Copy(Content, 1, InsertPos);
    After  := Copy(Content, InsertPos + 1, Length(Content));
    Content := Before + #13#10 + KeyLine + After;
  end

  // Cas 3 : pas de <appSettings> — on insère le bloc avant </configuration>
  else
  begin
    InsertPos := Pos('</configuration>', Content);
    if InsertPos = 0 then
    begin
      Log('ERREUR : balise </configuration> absente — configuration impossible.');
      Exit;
    end;
    Before := Copy(Content, 1, InsertPos - 1);
    After  := Copy(Content, InsertPos, Length(Content));
    Content := Before +
               '  <appSettings>' + #13#10 +
               KeyLine + #13#10 +
               '  </appSettings>' + #13#10 +
               After;
  end;

  SaveStringToFile(ConfigPath, AnsiString(Content), False);
  Log('URL API écrite dans ' + ConfigPath + ' : ' + ApiUrl);
end;

// ─────────────────────────────────────────────────────────────────────────────
// Déclenchement après copie des fichiers
// ─────────────────────────────────────────────────────────────────────────────
procedure CurStepChanged(CurStep: TSetupStep);
begin
  if CurStep = ssPostInstall then
    WriteApiUrlToConfig(ApiUrlPage.Values[0]);
end;
